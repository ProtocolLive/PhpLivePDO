<?php
//Protocol Corporation Ltda.
//https://github.com/ProtocolLive/PhpLiveDb

namespace ProtocolLive\PhpLiveDb;
use BackedEnum;
use PDO;
use PDOException;

/**
 * @version 2023.05.25.00
 */
class Insert
extends Basics{
  protected array $Fields = [];

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

  public function FieldAdd(
    string|BackedEnum $Field,
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
    if($Field instanceof BackedEnum):
      $Field = $Field->value;
    endif;
    $this->Fields[$Field] = new Field(
      $Field,
      $Value,
      $Type
    );
    return $this;
  }

  private function QueryBuild():void{
    $this->Query = 'insert into ' . $this->Table . '(';
    $this->InsertFields();

    if($this->Prefix !== null):
      $this->Query = str_replace('##', $this->Prefix . '_', $this->Query);
    endif;
  }

  public function QueryGet():string{
    $this->QueryBuild();
    return $this->Query;
  }

  public function RunFromSelect(
    string $Fields,
    Select $Select,
    bool $Debug = false,
    bool $Log = false,
    int $LogEvent = null,
    int $LogUser = null
  ):void{
    $this->Query = 'insert into ' . $this->Table . '(' . $Fields . ') ';
    $this->Query .= $Select->QueryGet();

    if($this->Prefix !== null):
      $this->Query = str_replace('##', $this->Prefix . '_', $this->Query);
    endif;
    $statement = $this->Conn->query($this->Query);

    $query = $this->LogAndDebug($statement, $Debug, $Log, $LogEvent, $LogUser);

    if($this->OnRun !== null):
      call_user_func_array(
        $this->OnRun,
        [
          'Query' => $query,
          'Result' => null,
          'Time' => $this->Duration(),
        ]
      );
    endif;
  }

  protected function InsertFields():void{
    foreach($this->Fields as $field):
      $this->Query .= $field->Name . ',';
    endforeach;
    $this->Query = substr($this->Query, 0, -1) . ') values(';
    foreach($this->Fields as $id => $field):
      if($field->Type === Types::Null):
        $this->Query .= 'null,';
      elseif($field->Type === Types::Sql):
        $this->Query .= $field->Value . ',';
      else:
        $this->Query .= ':' . ($field->CustomPlaceholder ?? $field->Name) . ',';
      endif;
    endforeach;
    $this->Query = substr($this->Query, 0, -1) . ')';
  }

  /**
   * @throws PDOException
   */
  public function Run(
    bool $Debug = false,
    bool $HtmlSafe = true,
    bool $TrimValues = true,
    bool $Log = false,
    int $LogEvent = null,
    int $LogUser = null
  ):int{
    $FieldsCount = count($this->Fields);
    if($FieldsCount === 0):
      return null;
    endif;

    $this->QueryBuild();
    $statement = $this->Conn->prepare($this->Query);

    $this->Bind($statement, $this->Fields, $HtmlSafe, $TrimValues);

    $statement->execute();
    $return = $this->Conn->lastInsertId();

    $query = $this->LogAndDebug($statement, $Debug, $Log, $LogEvent, $LogUser);

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
}