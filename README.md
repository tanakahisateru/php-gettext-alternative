# Gettext's po file parser.

The parsed result would be cached into your file instead of Apache's memory.

Gettext is chached in the process memory, so PHP's gettext is cached in Apache,
then we can't flush it.

In the other hand, it can be flushed simply by deleting file. But you don't
do it because the cache file is updated by saving corresponding `*.po` file.


## Usage

```php
$parser = new POParser('path/to/cache/dir');
$translations = $parser->parse('path/to/LC_MESSAGES/messages.po');

$text = isset($translations[$key]) ? $translations[$key] : $key;
```
## Appendix
You can use AlternativeGetTextTranslator instead of PHPTAL_GetTextTranslator.

```php
// $tr = new PHPTAL_GetTextTranslator();
$tr = new AlternativeGetTextTranslator('path/to/cache/dir');
```

See http://phptal.org/manual/en/split/gettext.html#php-in-phptal

