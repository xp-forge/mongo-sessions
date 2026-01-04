<?php namespace web\session\mongo;

use com\mongodb\Document;
use web\session\{Persistence, SessionInvalid};

class Session extends Persistence {
  const ENCODE= ['%' => '%25', '.' => '%2e'];
  private $collection, $document;

  /**
   * Creates a new file-based session
   *
   * @param  web.session.Sessions $sessions
   * @param  com.mongodb.Collection $collection
   * @param  com.mongodb.Document $document
   * @param  int $expires
   * @param  bool $detached
   */
  public function __construct($sessions, $collection, $document, $expires, $detached= false) {
    parent::__construct($sessions, $detached, $expires);
    $this->collection= $collection;
    $this->document= $document;
  }

  /** @return string */
  public function id() { return $this->document->id()->string(); }

  /** @return void */
  public function destroy() {
    $this->expires= time() - 1;
    $this->detached= false;
    $this->collection->delete($this->document->id());
  }

  /**
   * Returns all session keys
   *
   * @return string[]
   */
  public function keys() {
    if (time() >= $this->expires) {
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
   * @param  string $op
   * @param  string $name
   * @param  var $value
   * @return com.mongodb.Document
   * @throws web.session.SessionInvalid
   */
  private function update($op, $name, $value) {

    // If nothing was updated this means the session was deleted in the database
    // in the meantime, e.g. manually, by a cleanup procedure, or by a TTL index.
    return $this->collection
      ->modify($this->document->id(), [$op => ['values.'.strtr($name, self::ENCODE) => $value]])
      ->document() ?? new SessionInvalid($this->id())
    ;
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
    if (time() >= $this->expires) {
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
    if (time() >= $this->expires) {
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
    if (time() >= $this->expires) {
      throw new SessionInvalid($this->id());
    }

    $this->document= $this->update('$unset', $name, '');
  }
}