<?php namespace web\session\mongo;

use com\mongodb\Document;
use web\session\ISession;
use web\session\SessionInvalid;

class Session implements ISession {
  const ENCODE= ['%' => '%25', '.' => '%2e'];
  private $sessions, $collection, $document, $timeout, $new;

  /**
   * Creates a new file-based session
   *
   * @param  web.session.Sessions $sessions
   * @param  com.mongodb.Collection $collection
   * @param  com.mongodb.Document $document
   * @param  int $timeout
   * @param  bool $new
   */
  public function __construct($sessions, $collection, $document, $timeout, $new= false) {
    $this->sessions= $sessions;
    $this->collection= $collection;
    $this->timeout= $timeout;
    $this->new= $new;

    // Migrate old session layout
    if (!isset($document['values'])) {
      $values= [];
      foreach ($document->properties() as $key => $value) {
        '_' === $key[0] || $values[strtr($key, self::ENCODE)]= $value;
      }
      $document['values']= $values;
    }
    $this->document= $document;
  }

  /** @return string */
  public function id() { return $this->document->id()->string(); }

  /** @return bool */
  public function valid() {
    return time() < $this->timeout;
  }

  /** @return void */
  public function destroy() {
    $this->timeout= time() - 1;
    $this->collection->delete($this->document->id());
  }

  /**
   * Returns all session keys
   *
   * @return string[]
   */
  public function keys() {
    if (time() >= $this->timeout) {
      throw new SessionInvalid($this->id());
    }

    $r= [];
    foreach ($this->document['values'] as $key => $_) {
      $r[]= rawurldecode($key);
    }
    return $r;
  }

  /**
   * Update document in MongoDB with the given operations. Used by
   * `register()` and `remove()`.
   *
   * @param  string $operation
   * @param  string $name
   * @param  var $value
   * @return com.mongodb.Document
   * @throws web.session.SessionInvalid
   */
  private function update($operation, $name, $value) {
    $arguments= [
      'query'  => ['_id' => $this->document->id()],
      'update' => [$operation => ['values.'.strtr($name, self::ENCODE) => $value]],
      'new'    => true,
      'upsert' => false,
    ];

    // If nothing was updated this means the session was deleted in the database
    // in the meantime, e.g. manually, by a cleanup procedure, or by a TTL index.
    $value= $this->collection->run('findAndModify', $arguments)->value();
    if (!$value['lastErrorObject']['updatedExisting']) {
      throw new SessionInvalid($this->id());
    }

    return new Document($value['value']);
  }

  /**
   * Registers a value - writing it to the session
   *
   * @param  string $name
   * @param  var $value
   * @return void
   * @throws web.session.SessionInvalid
   */
  public function register($name, $value) {
    if (time() >= $this->timeout) {
      throw new SessionInvalid($this->id());
    }

    $this->document= $this->update('$set', $name, $value);
  }

  /**
   * Retrieves a value - reading it from the session
   *
   * @param  string $name
   * @param  var $default
   * @return var
   * @throws web.session.SessionInvalid
   */
  public function value($name, $default= null) {
    if (time() >= $this->timeout) {
      throw new SessionInvalid($this->id());
    }

    return $this->document['values'][strtr($name, self::ENCODE)] ?? $default;
  }

  /**
   * Removes a value - deleting it from the session
   *
   * @param  string $name
   * @return bool
   * @throws web.session.SessionInvalid
   */
  public function remove($name) {
    if (time() >= $this->timeout) {
      throw new SessionInvalid($this->id());
    }

    $this->document= $this->update('$unset', $name, '');
  }

  /**
   * Closes this session
   *
   * @return void
   */
  public function close() {
    // NOOP
  }

  /**
   * Transmits this session to the response
   *
   * @param  web.Response $response
   * @return void
   */
  public function transmit($response) {
    if ($this->new) {
      $this->sessions->attach($this, $response);
      $this->new= false;
    } else if (time() >= $this->timeout) {
      $this->sessions->detach($this, $response);
    }
  }
}