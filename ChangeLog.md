MongoDB Sessions change log
===========================

## ?.?.? / ????-??-??

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