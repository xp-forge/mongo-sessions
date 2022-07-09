<?php namespace web\session\mongo\unittest;

use com\mongodb\result\{Insert, Update, Delete, Cursor};
use com\mongodb\{Collection, Document, Session, Int64, ObjectId};
use lang\IllegalStateException;
use unittest\{Assert, Expect, Test};
use util\{Date, Dates};
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
        switch ($name) {
          case 'listIndexes':
            return [
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
            ];

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

            return [
              'lastErrorObject' => ['n' => 1, 'updatedExisting' => true],
              'value'           => $result->properties()
            ];

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
      '_created' => Date::now(),
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
      '_created' => Dates::subtract(Date::now(), 3601),
    ])]);

    $sessions= (new InMongoDB($collection))->lasting(3600);
    $session= $sessions->open($id->string());

    Assert::null($session);
    Assert::false($collection->present($id->string()));
  }

  #[Test]
  public function open_old_session() {
    $id= ObjectId::create();
    $collection= $this->collection([new Document([
      '_id'      => $id,
      '_created' => time(),
    ])]);

    $sessions= new InMongoDB($collection);
    $session= $sessions->open($id->string());

    Assert::instance(ISession::class, $session);
    Assert::true($collection->present($session->id()));
  }

  #[Test]
  public function value() {
    $id= ObjectId::create();
    $collection= $this->collection([new Document([
      '_id'      => $id,
      '_created' => Date::now(),
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
      '_created' => Date::now(),
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
      '_created' => Date::now(),
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
      '_created' => Date::now(),
    ])]);

    $sessions= new InMongoDB($collection);
    $session= $sessions->open($id->string());
    $session->destroy();

    $session->value('@@any@@');
  }

  #[Test]
  public function run_gc() {
    $sessions= new InMongoDB($this->collection([]));
    Assert::equals(0, $sessions->gc());
  }

  #[Test]
  public function gc_returns_number_of_expired_sessions() {
    $duration= 3600;
    $id= ObjectId::create();
    $collection= $this->collection([new Document([
      '_id'      => $id,
      '_created' => Dates::subtract(Date::now(), $duration + 1),
    ])]);

    $sessions= (new InMongoDB($collection))->lasting($duration);
    Assert::equals(1, $sessions->gc());
  }

  #[Test]
  public function expiry_time_fetched_from_ttl_index() {
    $sessions= new InMongoDB($this->collection([]), InMongoDB::USING_TTL);
    Assert::equals(1800, $sessions->duration());
  }

  #[Test, Expect(IllegalStateException::class)]
  public function expiry_time_cannot_be_modified_when_ttl_indexes_are_used() {
    $sessions= new InMongoDB($this->collection([]), InMongoDB::USING_TTL);
    $sessions->lasting(3600);
  }

  #[Test]
  public function gc_is_a_noop_when_ttl_indexes_are_used() {
    $duration= 3600;
    $id= ObjectId::create();
    $collection= $this->collection([new Document([
      '_id'      => $id,
      '_created' => Dates::subtract(Date::now(), $duration + 1),
    ])]);

    $sessions= (new InMongoDB($collection, InMongoDB::USING_TTL));
    Assert::equals(0, $sessions->gc());
  }
}