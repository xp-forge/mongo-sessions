MongoDB Sessions change log
===========================

## ?.?.? / ????-??-??

## 3.0.0 / ????-??-??

This major release drops support for old session layouts with keys in
the document root. Sessions with these will be regarded invalid, users
will need to re-authenticate.

* **Heads up**: Dropped support for PHP < 7.4, see xp-framework/rfc#343
  (@thekid)
* Merged PR #9: Drop support for MongoDB v1 - @thekid

## 2.2.1 / 2024-09-22

* Correctly persist migrated substructure, fixing session modifications
  (@thekid)

## 2.2.0 / 2024-09-14

* Merged PR #8: Refactor document substructure, fixing issue #6 (handling
  of special characters `.` and `$`) while doing so.
  (@thekid)

## 2.1.0 / 2024-03-24

* Made compatible with XP 12 - @thekid

## 2.0.0 / 2024-02-04

The second major release drops support for old XP versions, adding forward
compatibility with the upcoming PHP version at the same time.

* Implemented xp-framework/rfc#341: Drop XP <= 9 compatibility - @thekid
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