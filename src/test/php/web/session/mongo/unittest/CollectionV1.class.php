<?php namespace web\session\mongo\unittest;

use com\mongodb\result\{Cursor, Delete, Insert, Run, Update};
use com\mongodb\{Collection, Document, Int64, ObjectId, Session};

class CollectionV1 extends Collection {
  private $lookup= [];

  public function __construct($documents) {
    foreach ($documents as $document) {
      $this->lookup[$document->id()->string()]= $document;
    }
  }

  public function present($id) {
    return isset($this->lookup[$id]);
  }

  public function find($query= [], Session $session= null): Cursor {

    // Query will always be an ObjectId
    $result= $this->lookup[$query->string()] ?? null;
    return new Cursor(null, $session, [
      'firstBatch' => [$result->properties()],
      'id'         => new Int64(0),
      'ns'         => 'test.sessions'
    ]);
  }

  public function insert($arg, Session $session= null): Insert {

    // Argument will always be a Document
    $arg['_id']= ObjectId::create();
    $this->lookup[$arg['_id']->string()]= $arg;
    return new Insert([], [$arg['_id']]);
  }

  public function run($name, array $params= [], $method= 'write', Session $session= null) {
    switch ($name) {
      case 'listIndexes':
        return new Run(null, null, ['body' => [
          'ok'     => 1,
          'cursor' => [
            'id'         => new Int64(0),
            'ns'         => 'test.sessions',
            'firstBatch' => [[
              'v'                  => 2,
              'key'                => ['_created' => 1],
              'name'               => '_created_1',
              'expireAfterSeconds' => 1800,
            ]]
          ]
        ]]);

      case 'findAndModify':

        // Query will always be an ObjectId
        $result= &$this->lookup[$params['query']['_id']->string()];
        switch (key($params['update'])) {
          case '$set': 
            foreach ($params['update']['$set'] as $name => $value) {
              $result[$name]= $value;
            }
            break;

          case '$unset': 
            foreach ($params['update']['$unset'] as $name => $_) {
              unset($result[$name]);
            }
            break;
        }

        return new Run(null, null, ['body' => [
          'lastErrorObject' => ['n' => 1, 'updatedExisting' => true],
          'value'           => $result->properties()
        ]]);

      default:
        throw new IllegalStateException('Unreachable code - command "'.$name.'"');
    }
  }

  public function delete($query, Session $session= null): Delete {

    // Query will either be an ObjectId or [_created => [$lt => time()]]
    if ($query instanceof ObjectId) {
      unset($this->lookup[$query->string()]);
      $n= 1;
    } else {
      $n= 0;
      foreach ($this->lookup as $id => $document) {
        if ($document['_created']->isBefore($query['_created']['$lt'])) {
          unset($this->lookup[$id]);
          $n++;
        }
      }
    }
    return new Delete(['n' => $n], [$query]);
  }
}