<?php
//Protocol Corporation Ltda.
//https://github.com/ProtocolLive/PhpLiveDb
//2023.01.21.00

namespace ProtocolLive\PhpLiveDb;
use PDOException;

final class InsertUpdate extends Insert{
  private function BuildQuery():bool{
    if(count($this->Fields) === 0):
      return false;
    endif;

    $this->Query = 'insert into ' . $this->Table . '(';
    $this->InsertFields();

    $this->Query = str_replace('##', $this->Prefix . '_', $this->Query);
    $this->Query .= ' on duplicate key update ';
    foreach($this->Fields as $field):
      if($field->InsertUpdate):
        $this->Query .= ($field->CustomPlaceholder ?? $field->Name);
        $this->Query .= '=values(' . ($field->CustomPlaceholder ?? $field->Name) . '),';
      endif;
    endforeach;
    $this->Query = substr($this->Query, 0, -1);
    return true;
  }

  public function FieldAdd(
    string $Field,
    string|bool|null $Value,
    Types $Type,
    bool $BlankIsNull = true,
    bool $Update = false
  ):self{
    if($BlankIsNull and $Value === ''):
      $Value = null;
    endif;
    if($Value === null):
      $Type = Types::Null;
    endif;
    $this->Fields[$Field] = new Field(
      $Field,
      $Value,
      $Type,
      InsertUpdate: $Update
    );
    return $this;
  }

  public function IdGet():int{
    return $this->Conn->lastInsertId();
  }

  public function QueryGet():string{
    self::BuildQuery();
    return $this->Query;
  }

  /**
   * @return void
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
    if(self::BuildQuery() === false):
      return 0;
    endif;
    $statement = $this->Conn->prepare($this->Query);

    $this->Bind($statement, $this->Fields, $HtmlSafe, $TrimValues);

    $statement->execute();

    $query = $this->LogAndDebug($statement, $Debug, $Log, $LogEvent, $LogUser);

    if($this->OnRun !== null):
      call_user_func_array(
        $this->OnRun,
        [
          'Query' => $query,
          'Result' => 0,
          'Time' => $this->Duration(),
        ]
      );
    endif;
    return 0;
  }
}