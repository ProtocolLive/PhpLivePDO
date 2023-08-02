<?php
//Protocol Corporation Ltda.
//https://github.com/ProtocolLive/PhpLiveDb

namespace ProtocolLive\PhpLiveDb;
use BackedEnum;
use PDO;
use PDOException;
use ProtocolLive\PhpLiveDb\Operators;
use UnitEnum;

/**
 * @version 2023.08.02.00
 */
final class Update
extends Basics{
  private array $Fields = [];
  private array $Wheres = [];

  public function __construct(
    PDO $Conn,
    string $Table,
    string $Prefix = null,
    callable $OnRun = null
  ){
    $this->Conn = $Conn;
    $this->Table = $Table;
    $this->Prefix = $Prefix;
    $this->OnRun = $OnRun;
  }

  private function BuildQuery():void{
    $WheresCount = count($this->Wheres);

    $this->Query = 'update ' . $this->Table . ' set ';
    $this->UpdateFields();
    if($WheresCount > 0):
      $this->BuildWhere($this->Wheres);
    endif;

    if($this->Prefix !== null):
      $this->Query = str_replace('##', $this->Prefix . '_', $this->Query);
    endif;
  }

  public function FieldAdd(
    string|UnitEnum $Field,
    string|bool|null $Value,
    Types $Type,
    bool $BlankIsNull = true
  ):self{
    if($BlankIsNull and $Value === ''):
      $Value = null;
    endif;
    if($Value === null):
      $Type = Types::Null;
    endif;
    if($Field instanceof UnitEnum):
      $Field = $Field->value ?? $Field->name;
    endif;
    $this->Fields[$Field] = new Field(
      $Field,
      $Value,
      $Type
    );
    return $this;
  }

  public function QueryGet():string{
    self::BuildQuery();
    return $this->Query;
  }

  /**
   * @throws PDOException
   */
  public function Run(
    bool $Debug = false,
    bool $HtmlSafe = true,
    bool $TrimValues = true,
    bool $Log = false,
    int|BackedEnum $LogEvent = null,
    int $LogUser = null
  ):int{
    self::BuildQuery();
    $WheresCount = count($this->Wheres);
    $statement = $this->Conn->prepare($this->Query);

    $this->Bind($statement, $this->Fields, $HtmlSafe, $TrimValues);
    if($WheresCount > 0):
      $this->Bind($statement, $this->Wheres, $HtmlSafe, $TrimValues);
    endif;
    
    $statement->execute();
    $return = $statement->rowCount();

    $query = $this->LogAndDebug(
      $statement,
      $Debug,
      $Log,
      $LogEvent,
      $LogUser
    );

    if($this->OnRun !== null):
      call_user_func_array(
        $this->OnRun,
        [
          'Query' => $query,
          'Result' => $return,
          'Time' => $this->Duration(),
        ]
      );
    endif;

    return $return;
  }

  private function UpdateFields():void{
    foreach($this->Fields as $id => $field):
      $this->Query .= parent::Reserved($field->Name) . '=';
      if($field->Type === Types::Null):
        $this->Query .= 'null';
        unset($this->Fields[$id]);
      elseif($field->Type === Types::Sql):
        $this->Query .= $field->Value;
        unset($this->Fields[$id]);
      else:
        $this->Query .= ':' . $field->Name;
      endif;
      $this->Query .= ',';
    endforeach;
    $this->Query = substr($this->Query, 0, -1);
  }

  /**
   * @param string|UnitEnum $Field Field name
   * @param string|bool $Value Field value. Can be null in case of use another field value. If null, sets the $Operator to Operator::Null
   * @param Types $Type Field type. Can be null in case of Operator::IsNull. Are changed to Types::Null if $Value is null
   * @param Operators $Operator Comparison operator. Operator::Sql sets NoBind to true
   * @param AndOr $AndOr Relation with the prev field
   * @param Parenthesis $Parenthesis Open or close parenthesis
   * @param string $CustomPlaceholder Substitute the field name as placeholder
   * @param bool $BlankIsNull Convert '' to null
   * @param bool $NoBind Don't bind values. Are set to true if Operator is Operators::Sql
   */
  public function WhereAdd(
    string|UnitEnum $Field,
    string|bool $Value = null,
    Types $Type = null,
    Operators $Operator = Operators::Equal,
    AndOr $AndOr = AndOr::And,
    Parenthesis $Parenthesis = Parenthesis::None,
    string $CustomPlaceholder = null,
    bool $BlankIsNull = true,
    bool $NoBind = false
  ):self{
    if($Field instanceof UnitEnum):
      $Field = $Field->value ?? $Field->name;
    endif;
    if(isset($this->Wheres[$CustomPlaceholder ?? $Field])):
      throw new PDOException(
        'The where condition "' . ($CustomPlaceholder ?? $Field) . '" already added',
      );
    endif;
    if($CustomPlaceholder === null):
      $this->FieldNeedCustomPlaceholder(($Field));
    endif;
    if($BlankIsNull and $Value === ''):
      $Value = null;
      $Type = Types::Null;
    endif;
    $this->Wheres[$CustomPlaceholder ?? $Field] = new Field(
      $Field,
      $Value,
      $Type,
      $Operator,
      $AndOr,
      $Parenthesis,
      $CustomPlaceholder,
      $BlankIsNull,
      false,
      $NoBind
    );
    return $this;
  }
}