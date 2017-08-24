# phpunit-progressing-result-printer
Result printer for PHPUnit that prints results on the fly

## Install

Via Composer

``` bash
$ composer require alcaeus/phpunit-progressing-result-printer --dev
```

To enable the result printer, add the following two lines to the opening element of your `phpunit.xml`:

``` php
printerFile="vendor/alcaeus/phpunit-progressing-result-printer/lib/Alcaeus/ResultPrinter/ProgressingResultPrinter.php"
printerClass="Alcaeus\ResultPrinter\ProgressingResultPrinter"
```
