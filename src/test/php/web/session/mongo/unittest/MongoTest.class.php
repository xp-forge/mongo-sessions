<?php namespace web\session\mongo\unittest;

use com\mongodb\result\{Insert, Update, Delete, Cursor};
use com\mongodb\{Collection, Document, Session, Int64, ObjectId};
use unittest\{Assert, Expect, Test};
use web\session\{ISession, InMongoDB, SessionInvalid};

class MongoTest {

  /** Returns a MongoDB collection with documents */
  private function collection($documents) {
    return new class($documents) extends Collection {
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

      public function command($name, array $params= [], Session $session= null) {

        // Command will be "findAndModify", query will always be an ObjectId
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

        return [
          'lastErrorObject' => ['n' => 1, 'updatedExisting' => true],
          'value'           => $result->properties()
        ];
      }

      public function delete($query, Session $session= null): Delete {

        // Query will always be an ObjectId
        unset($this->lookup[$query->string()]);
        return new Delete([], [$query]);
      }
    };
  }

  #[Test]
  public function create_session() {
    $collection= $this->collection([]);

    $sessions= new InMongoDB($collection);
    $session= $sessions->create();

    Assert::instance(ISession::class, $session);
    Assert::true($collection->present($session->id()));
  }

  #[Test]
  public function open_session() {
    $id= ObjectId::create();
    $collection= $this->collection([new Document([
      '_id'      => $id,
      '_created' => time()
    ])]);

    $sessions= new InMongoDB($collection);
    $session= $sessions->open($id->string());

    Assert::instance(ISession::class, $session);
    Assert::true($collection->present($session->id()));
  }

  #[Test]
  public function open_expired_session() {
    $id= ObjectId::create();
    $collection= $this->collection([new Document([
      '_id'      => $id,
      '_created' => time() - 3601
    ])]);

    $sessions= (new InMongoDB($collection))->lasting(3600);
    $session= $sessions->open($id->string());

    Assert::null($session);
    Assert::false($collection->present($id->string()));
  }

  #[Test]
  public function value() {
    $id= ObjectId::create();
    $collection= $this->collection([new Document([
      '_id'      => $id,
      '_created' => time(),
      'user'     => 'test',
    ])]);

    $sessions= new InMongoDB($collection);
    $session= $sessions->open($id->string());

    Assert::equals('test', $session->value('user'));
  }

  #[Test]
  public function register() {
    $id= ObjectId::create();
    $collection= $this->collection([new Document([
      '_id'      => $id,
      '_created' => time(),
    ])]);

    $sessions= new InMongoDB($collection);
    $session= $sessions->open($id->string());
    $session->register('user', 'test');

    Assert::equals('test', $session->value('user'));
  }

  #[Test]
  public function remove() {
    $id= ObjectId::create();
    $collection= $this->collection([new Document([
      '_id'      => $id,
      '_created' => time(),
      'user'     => 'test',
    ])]);

    $sessions= new InMongoDB($collection);
    $session= $sessions->open($id->string());
    $session->remove('user');

    Assert::null($session->value('user'));
  }

  #[Test, Expect(SessionInvalid::class)]
  public function invalid_session() {
    $id= ObjectId::create();
    $collection= $this->collection([new Document([
      '_id'      => $id,
      '_created' => time(),
    ])]);

    $sessions= new InMongoDB($collection);
    $session= $sessions->open($id->string());
    $session->destroy();

    $session->value('@@any@@');
  }
}