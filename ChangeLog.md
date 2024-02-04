MongoDB Sessions change log
===========================

## ?.?.? / ????-??-??

* Added PHP 8.4 to the test matrix - @thekid

## 1.3.0 / 2023-08-19

* Made this library compatible with `xp-forge/mongodb` 2.0.0 - @thekid
* Merged PR #5: Migrate to new testing library - @thekid

## 1.2.0 / 2022-07-09

* Upgraded `xp-forge/mongodb` dependency to version 1.4.0, using `read`
  semantics for the *listIndexes* command
  (@thekid)

## 1.1.0 / 2022-07-09

* Implemented #3: Support TTL indexes. Using these delegates removing
  expired sessions to the MongoDB server, speeding up session creation.
  See https://www.mongodb.com/docs/manual/core/index-ttl/
  (@thekid)

## 1.0.0 / 2022-06-11

The first major release was created after using this library in production
for more than two months.

* Merged PR #4: Add `InMongoDB::gc()` to run garbage collection - @thekid

## 0.4.1 / 2022-06-11

* Added compatibility with `xp-forge/sessions` version 3.0 - @thekid

## 0.4.0 / 2022-04-19

* Merged PR #2: Cleanup sessions during create - @thekid

## 0.3.0 / 2022-04-13

* Implemented feature suggested in issue #1: Use `findAndModify` MongoDB
  command to update session in the database *and* refresh the read view
  at the same time
  (@thekid)

## 0.2.0 / 2022-04-12

* Fixed PHP 8.2 compatibility - @thekid

## 0.1.0 / 2022-04-12

* Hello World! First release - @thekid