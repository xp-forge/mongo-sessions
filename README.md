MongoDB Sessions
================

[![Build status on GitHub](https://github.com/xp-forge/mongo-sessions/workflows/Tests/badge.svg)](https://github.com/xp-forge/mongo-sessions/actions)
[![XP Framework Module](https://raw.githubusercontent.com/xp-framework/web/master/static/xp-framework-badge.png)](https://github.com/xp-framework/core)
[![BSD Licence](https://raw.githubusercontent.com/xp-framework/web/master/static/licence-bsd.png)](https://github.com/xp-framework/core/blob/master/LICENCE.md)
[![Requires PHP 7.0+](https://raw.githubusercontent.com/xp-framework/web/master/static/php-7_0plus.svg)](http://php.net/)
[![Supports PHP 8.0+](https://raw.githubusercontent.com/xp-framework/web/master/static/php-8_0plus.svg)](http://php.net/)
[![Latest Stable Version](https://poser.pugx.org/xp-forge/mongo-sessions/version.png)](https://packagist.org/packages/xp-forge/mongo-sessions)

[MongoDB](https://www.mongodb.com/)-based [sessions implementation](https://github.com/xp-forge/sessions).

Example
-------

```php
use web\session\InMongoDB;
use com\mongodb\MongoConnection;

$conn= new MongoConnection('mongo://localhost');
$sessions= new InMongoDB($conn->collection('test', 'sessions'));
``` 
