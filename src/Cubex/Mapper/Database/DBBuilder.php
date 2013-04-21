<?php
/**
 * @author  brooke.bryan
 */

namespace Cubex\Mapper\Database;

use Cubex\Data\Attribute;
use Cubex\Database\DatabaseService;
use Cubex\Database\Schema\Column;
use Cubex\Database\Schema\DataType;
use Cubex\Helpers\Strings;
use Cubex\Sprintf\ParseQuery;

class DBBuilder
{
  /**
   * @var RecordMapper
   */
  protected $_mapper;
  /**
   * @var RecordMapper
   */
  protected $_emptyMapper;
  /**
   * @var Column[]
   */
  protected $_columns = [];
  protected $_tableName;
  protected $_database;
  protected $_connection;
  protected $_column;
  protected $_passed;
  protected $_mapperClass;

  public function __construct(DatabaseService $connection, RecordMapper $mapper)
  {
    $this->_connection  = $connection;
    $this->_mapper      = $mapper;
    $this->_mapperClass = get_class($mapper);
    $this->_emptyMapper = clone $mapper;
    $this->_reflect     = new \ReflectionObject($this->_mapper);

    $matches = array();
    if($connection->errorNo() == 1146) //Table does not exist
    {
      preg_match_all("/\w+/", $connection->errorMsg(), $matches);
      if($matches)
      {
        list(, $database, $table,) = $matches[0];
        $this->_tableName = $table;
        $this->_database  = $database;

        $this->createColumns();
        $this->_passed = $this->_connection->query($this->createDB());
      }
    }
    else if($connection->errorNo() == 1054) //Column does not exist
    {
      preg_match_all("/\w+/", $connection->errorMsg(), $matches);
      if($matches)
      {
        $this->_column    = $matches[0][2];
        $this->_tableName = $mapper->getTableName();
        $sql              = $this->_addColumn();
        if($sql != null)
        {
          $this->_passed = $this->_connection->query($sql);
        }
      }
    }
  }

  protected function _addColumn()
  {
    $sql = 'ALTER TABLE `' . $this->_tableName . '` ';

    $schema = call_user_func([$this->_mapperClass, 'schema']);
    $keys   = array_keys($schema);

    $cols = [];

    foreach($this->_mapper->getRawAttributes() as $attr)
    {
      $name = $this->_mapper->stringToColumnName($attr->name());
      if(!in_array($name, $keys))
      {
        $col = $this->_columnFromAttribute($attr);
        if($col !== null)
        {
          $cols[] = "ADD " . trim($col->createSql());
        }
      }
    }

    if(empty($cols))
    {
      return null;
    }

    $sql .= implode(", ", $cols);

    return $sql;
  }

  public function success()
  {
    return (bool)$this->_passed;
  }

  protected function _columnFromAttribute(Attribute $attr)
  {
    if(!$attr->saveToDatabase())
    {
      return null;
    }
    $name = $this->_mapper->stringToColumnName($attr->name());
    if($this->_mapper->getIdKey() == $name)
    {
      return null;
    }
    $uname = Strings::variableToUnderScore($name);

    $emptyAttribute = $this->_emptyMapper->getAttribute($name);

    $unsigned     = false;
    $allowNull    = true;
    $characterSet = $collation = null;
    $default      = $emptyAttribute->serialize($emptyAttribute->defaultValue());
    $options      = 150;
    $dataType     = DataType::VARCHAR;
    $annotation   = [];
    try
    {
      $comment = $this->_reflect->getProperty(
        $attr->sourceProperty()
      )->getDocComment();
      if(!empty($comment))
      {
        $comments = Strings::docCommentLines($comment);
        $comment  = '';
        foreach($comments as $comm)
        {
          if(substr($comm, 0, 1) == '@')
          {
            if(substr($comm, 0, 8) !== '@comment')
            {
              list($type, $detail) = explode(' ', substr($comm, 1));
              if(!empty($detail) && !empty($type))
              {
                $annotation[$type] = $detail;
              }
              continue;
            }
            else
            {
              $comm = substr($comm, 8);
            }
          }
          if(!empty($comm))
          {
            $comment .= $comm . "\n";
          }
        }
        $comment = implode(
          ", ",
          phutil_split_lines($comment, false)
        );
      }
      if(empty($comment))
      {
        $comment = null;
      }
    }
    catch(\Exception $e)
    {
      $comment = null;
    }
    $zero = $collation = null;

    if(substr($uname, -3) == '_id')
    {
      $dataType = DataType::INT;
      $options  = 10;
      $unsigned = true;
    }
    else if(substr($uname, -3) == '_at' || substr($uname, -5) == '_time')
    {
      $dataType = DataType::DATETIME;
    }
    else if(substr($uname, -3) == '_on' || substr($uname, -5) == '_date')
    {
      $dataType = DataType::DATE;
    }
    else if($attr->rawData() instanceof \DateTime)
    {
      $dataType = DataType::DATETIME;
    }

    if(!empty($annotation))
    {
      foreach($annotation as $k => $v)
      {
        switch(strtolower($k))
        {
          case 'default':
            $default = $v;
            break;
          case 'enumclass':
            if(class_exists($v))
            {
              $options  = new $v;
              $dataType = DataType::ENUM;
            }
            break;
          case 'length':
            $options = (int)$v;
            break;
          case 'datatype':
            $dataType = $v;
            break;
          case 'characterset':
          case 'charset':
            $characterSet = $v;
            break;
          case 'collation':
          case 'collate':
            $collation = $v;
            if($characterSet === null)
            {
              $characterSet = head(explode('_', $collation));
            }
            break;
          case 'allownull':
            $allowNull = (bool)$v;
            break;
        }
      }
    }

    return new Column(
      $name, $dataType, $options, $unsigned, $allowNull, $default,
      false, $comment, $zero, $characterSet, $collation
    );
  }

  protected function _docExplosion($comment)
  {
    $comments = [];
    $comment  = substr($comment, 3, -2);
    foreach(explode("\n", $comment) as $comment)
    {
      $comment = trim(ltrim(trim($comment), '*'));
      if(!empty($comment))
      {
        $comments[] = $comment;
      }
    }
    return $comments;
  }

  public function createColumns()
  {
    $attrs = $this->_mapper->getRawAttributes();

    if(!$this->_mapper->isCompositeId())
    {
      $this->_columns[] = new Column(
        $this->_mapper->getIdKey(), DataType::INT, 10, true, false, null, true
      );
    }

    foreach($attrs as $attr)
    {
      $col = $this->_columnFromAttribute($attr);
      if($col !== null)
      {
        $this->_columns[] = $col;
      }
    }
  }

  public function createDB()
  {
    $columns    = $this->_columnSqls();
    $indexes    = $this->_getIndexes();
    $properties = $this->_getTableProperties();
    $content    = array_merge((array)$columns, (array)$indexes);

    $sql = "CREATE TABLE ";
    $sql .= "`" . $this->_database . "`.`" . $this->_tableName . "`";
    $sql .= "(" . implode(",", $content) . ") ";
    $sql .= implode(" ", $properties);

    echo "\n\n" . $sql . "\n\n";

    return $sql;
  }

  protected function _getIndexes()
  {
    $indexes  = [];
    $comments = Strings::docCommentLines($this->_reflect->getDocComment());
    foreach($comments as $comment)
    {
      list($type, $desc) = explode(" ", $comment, 2);
      $on = implode("`,`", explode(",", str_replace(' ', '', $desc)));
      switch($type)
      {
        case '@index':
          $indexes[] = " INDEX(`$on`) ";
          break;
        case '@fulltext':
          $indexes[] = " FULLTEXT(`$on`) ";
          break;
        case '@unique':
          $indexes[] = " UNIQUE(`$on`) ";
          break;
      }
    }
    return $indexes;
  }

  protected function _getTableProperties()
  {
    $engine   = 'MYISAM';
    $comments = [];
    $charset  = $collation = null;
    $doclines = Strings::docCommentLines($this->_reflect->getDocComment());
    foreach($doclines as $docline)
    {
      list($type, $val) = explode(" ", $docline, 2);
      switch($type)
      {
        case '@engine':
          $engine = $val;
          break;
        case '@comment':
          $comments[] = $val;
          break;
        case '@collate':
        case '@collation':
          $collation = $val;
          if($charset === null)
          {
            $charset = head(explode('_', $val));
          }
          break;
        case '@charset':
        case '@characterset':
          $charset = head(explode('_', $val));
          break;
        default:
          if(substr($type, 0, 1) !== '@')
          {
            $comments[] = $val;
          }
          break;
      }
    }

    $props[] = 'ENGINE = ' . $engine;

    if($charset !== null)
    {
      $props[] = 'CHARACTER SET ' . $charset;
    }

    if($collation !== null)
    {
      $props[] = 'COLLATE ' . $collation;
    }
    if(!empty($comments))
    {
      $props[] = " COMMENT = '" . addslashes(implode(", ", $comments)) . "'";
    }

    return $props;
  }

  protected function _columnSqls()
  {
    $cols = [];

    if($this->_mapper->isCompositeId())
    {
      $idcomp     = $this->_mapper->getCompAttribute(
        $this->_mapper->getIdKey()
      );
      $primaryIds = $idcomp->attributeOrder();
    }
    else
    {
      $primaryIds = [];
    }

    foreach($this->_columns as $col)
    {
      if(in_array($col->name(), $primaryIds))
      {
        array_unshift($cols, $col->createSql());
      }
      else
      {
        $cols[] = $col->createSql();
      }
    }

    if($this->_mapper->isCompositeId())
    {
      $query  = ParseQuery::parse(
        $this->_mapper->conn(),
        [
        "PRIMARY KEY ( %LC )",
        $primaryIds
        ]
      );
      $cols[] = $query;
    }

    return $cols;
  }
}
