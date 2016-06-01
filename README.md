About
-----
TP-to-PHP converter convert TypeScript definition to PHP code

Is usefully when you want use [PHP-to-Javascript](https://github.com/tito10047/PHP-to-Javascript) 
converter and you miss php definition of your framework .


Installation and requirements
-----------------------------

The best way how to install TP-to-PHP is use a Composer:

```
php composer.phar require mostka/tptophp
```

Usage
-----

```php
\tptophp\convert($tsSourceTsFile,$outputPhpFile);
```