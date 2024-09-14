<?php namespace web\session;

use com\mongodb\{Collection, Document, ObjectId};
use lang\{IllegalArgumentException, IllegalStateException};
use util\Date;
use web\session\mongo\Session;

/**
 * Session factory connecting to a MongoDB server. Can use TTL indexes
 * to remove expired sessions automatically.
 *
 * @see   https://www.mongodb.com/
 * @see   https://www.mongodb.com/docs/manual/tutorial/expire-data/
 * @test  web.session.mongdo.unittest.MongoTest
 */
class InMongoDB extends Sessions {
  const USING_TTL = true;

  private $collection;
  private $useTTL= false;

  /** Creates a new MongoDB session */
  public function __construct(Collection $collection, bool $useTTL= false) {
    $this->collection= $collection;

    // Adapt timeout from TTL index
    if ($useTTL) {
      foreach ($this->collection->run('listIndexes', [], 'read')->cursor() as $index) {
        if ('_created' === key($index['key'])) {
          $this->lasting($index['expireAfterSeconds']);
          $this->useTTL= true;
          return;
        }
      }
      throw new IllegalArgumentException('No TTL index found on '.$collection->namespace());
    }
  }

  /**
   * Sets how long a session should last. Defaults to one day.
   *
   * @param  int|util.TimeSpan $duration
   * @return self
   * @throws lang.IllegalStateException when TTL indexes are in use
   */
  public function lasting($duration) {
    if ($this->useTTL) {
      throw new IllegalStateException('Cannot modify duration when TTL indexes are in use');
    }

    return parent::lasting($duration);
  }

  /**
   * Runs garbage collection, removing expired sessions. If a TTL index is
   * in use, this function is a NOOP and always return 0 as the MongoDB
   * server will have taken care of this for us!
   *
   * @see    https://www.mongodb.com/docs/manual/core/index-ttl/
   * @return int
   */
  public function gc() {
    if ($this->useTTL) return 0;

    $before= new Date(time() - $this->duration);
    return $this->collection->delete(['_created' => ['$lt' => $before]])->deleted();
  }

  /**
   * Creates a session
   *
   * @return web.session.ISession
   */
  public function create() {
    $now= time();
    $this->collection->insert($values= new Document(['_created' => new Date($now), 'values' => []]));

    // Clean up expired sessions while we're here.
    $this->gc();

    return new Session($this, $this->collection, $values, $now + $this->duration, true);
  }

  /**
   * Opens an existing and valid session. 
   *
   * @param  string $id
   * @return ?web.session.ISession
   */
  public function open($id) {
    $oid= new ObjectId($id);
    if ($doc= $this->collection->find($oid)->first()) {

      // Check for expired sessions not already taken care by TTL...
      $created= $doc['_created'] instanceof Date ? $doc['_created']->getTime() : $doc['_created'];
      $timeout= $created + $this->duration;
      if ($timeout > time()) {

        // Migrate old session layout
        if (!isset($doc['values'])) {
          $values= [];
          foreach ($doc->properties() as $key => $value) {
            '_' === $key[0] || $values[$key]= $value;
          }
          $doc['values']= $values;
        }
        return new Session($this, $this->collection, $doc, $timeout, false);
      }

      // ...and delete them
      $this->collection->delete($oid);
    }
    return null;
  }
}