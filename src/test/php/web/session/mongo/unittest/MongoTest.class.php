<?php namespace web\session\mongo\unittest;

use com\mongodb\{Collection, Document, ObjectId};
use lang\IllegalStateException;
use test\{Assert, Expect, Test, Values};
use util\{Date, Dates};
use web\session\{ISession, InMongoDB, SessionInvalid};

class MongoTest {

  #[Test]
  public function create_session() {
    $collection= new TestingCollection([]);

    $sessions= new InMongoDB($collection);
    $session= $sessions->create();

    Assert::instance(ISession::class, $session);
    Assert::true($collection->present($session->id()));
  }

  #[Test]
  public function open_session() {
    $id= ObjectId::create();
    $collection= new TestingCollection([new Document([
      '_id'      => $id,
      '_created' => Date::now(),
      'values'   => [],
    ])]);

    $sessions= new InMongoDB($collection);
    $session= $sessions->open($id->string());

    Assert::instance(ISession::class, $session);
    Assert::true($collection->present($session->id()));
  }

  #[Test]
  public function open_expired_session() {
    $id= ObjectId::create();
    $collection= new TestingCollection([new Document([
      '_id'      => $id,
      '_created' => Dates::subtract(Date::now(), 3601),
      'values'   => [],
    ])]);

    $sessions= (new InMongoDB($collection))->lasting(3600);
    $session= $sessions->open($id->string());

    Assert::null($session);
    Assert::false($collection->present($id->string()));
  }

  #[Test]
  public function open_session_without_values_substructure() {
    $id= ObjectId::create();
    $collection= new TestingCollection([new Document([
      '_id'      => $id,
      '_created' => Dates::subtract(Date::now(), 3601),
    ])]);

    $sessions= (new InMongoDB($collection))->lasting(3600);
    $session= $sessions->open($id->string());

    Assert::null($session);
    Assert::true($collection->present($id->string()));
  }

  #[Test]
  public function value() {
    $id= ObjectId::create();
    $collection= new TestingCollection([new Document([
      '_id'      => $id,
      '_created' => Date::now(),
      'values'   => ['user' => 'test'],
    ])]);

    $sessions= new InMongoDB($collection);
    $session= $sessions->open($id->string());

    Assert::equals('test', $session->value('user'));
  }

  #[Test]
  public function keys() {
    $id= ObjectId::create();
    $collection= new TestingCollection([new Document([
      '_id'      => $id,
      '_created' => Date::now(),
      'values'   => ['user' => 'test'],
    ])]);

    $sessions= new InMongoDB($collection);
    $session= $sessions->open($id->string());

    Assert::equals(['user'], $session->keys());
  }

  #[Test]
  public function register() {
    $id= ObjectId::create();
    $collection= new TestingCollection([new Document([
      '_id'      => $id,
      '_created' => Date::now(),
      'values'   => [],
    ])]);

    $sessions= new InMongoDB($collection);
    $session= $sessions->open($id->string());
    $session->register('user', 'test');

    Assert::equals('test', $session->value('user'));
  }

  #[Test]
  public function remove() {
    $id= ObjectId::create();
    $collection= new TestingCollection([new Document([
      '_id'      => $id,
      '_created' => Date::now(),
      'values'   => ['user' => 'test'],
    ])]);

    $sessions= new InMongoDB($collection);
    $session= $sessions->open($id->string());
    $session->remove('user');

    Assert::null($session->value('user'));
  }

  #[Test, Expect(SessionInvalid::class)]
  public function invalid_session() {
    $id= ObjectId::create();
    $collection= new TestingCollection([new Document([
      '_id'      => $id,
      '_created' => Date::now(),
      'values'   => [],
    ])]);

    $sessions= new InMongoDB($collection);
    $session= $sessions->open($id->string());
    $session->destroy();

    $session->value('@@any@@');
  }

  #[Test]
  public function run_gc() {
    $sessions= new InMongoDB(new TestingCollection([]));
    Assert::equals(0, $sessions->gc());
  }

  #[Test]
  public function gc_returns_number_of_expired_sessions() {
    $duration= 3600;
    $id= ObjectId::create();
    $collection= new TestingCollection([new Document([
      '_id'      => $id,
      '_created' => Dates::subtract(Date::now(), $duration + 1),
      'values'   => [],
    ])]);

    $sessions= (new InMongoDB($collection))->lasting($duration);
    Assert::equals(1, $sessions->gc());
  }

  #[Test]
  public function expiry_time_fetched_from_ttl_index() {
    $sessions= new InMongoDB(new TestingCollection([]), InMongoDB::USING_TTL);
    Assert::equals(1800, $sessions->duration());
  }

  #[Test, Expect(IllegalStateException::class)]
  public function expiry_time_cannot_be_modified_when_ttl_indexes_are_used() {
    $sessions= new InMongoDB(new TestingCollection([]), InMongoDB::USING_TTL);
    $sessions->lasting(3600);
  }

  #[Test]
  public function gc_is_a_noop_when_ttl_indexes_are_used() {
    $duration= 3600;
    $id= ObjectId::create();
    $collection= new TestingCollection([new Document([
      '_id'      => $id,
      '_created' => Dates::subtract(Date::now(), $duration + 1),
      'values'   => [],
    ])]);

    $sessions= (new InMongoDB($collection, InMongoDB::USING_TTL));
    Assert::equals(0, $sessions->gc());
  }

  #[Test, Values([['user.name', 'user%2ename'], ['%user', '%25user'], ['%5fuser', '%255fuser']])]
  public function special_characters_are_escaped($key, $stored) {
    $id= ObjectId::create();
    $collection= new TestingCollection([new Document([
      '_id'      => $id,
      '_created' => Date::now(),
      'values'   => [$stored => 'test'],
    ])]);

    $sessions= new InMongoDB($collection);
    $session= $sessions->open($id->string());

    Assert::equals('test', $session->value($key));
  }
}