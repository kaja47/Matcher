Atrox\Matcher
=============

Matcher - powerful tool for extracting data from XML and HTML using XPath and
pure magic.


Examples:
---------

```php
use Atrox\Matcher;

$m = Matcher::multi('//div[@id="siteTable"]/div[contains(@class, "thing")]', [
  'id'    => '@data-fullname',
  'title' => './/p[@class="title"]/a',
  'url'   => './/p[@class="title"]/a/@href',
  'date'  => './/time/@datetime',
  'img'   => 'a[contains(@class, "thumbnail")]/img/@src',
  'votes' => (object) [
    'ups'   => '@data-ups',
    'downs' => '@data-downs',
    'rank'  => 'span[@class="rank"]',
    'score' => './/div[contains(@class, "score")]',
  ],
])->fromHtml();

$f = file_get_contents('http://www.reddit.com/');

$extractedData = $m($f);
```

result:

```php
[
  [
    "id"    => "t3_1ep0c5",
    "title" => "Obligatory funny cat pictures.",
    "url"   => "http://imgur.com/sGu0pEk",
    "date"  => "2013-05-20T14:16:24+00:00",
    "img"   => "http://e.thumbs.redditmedia.com/MZjtg3UnZ8MOVjcd.jpg",
    "votes" => (object) [
      "ups"   => "115036",
      "downs" => "10266",
      "rank"  => "1",
      "score" => "105650"
    ]
  ],
  [
    ...
  ]
]
```

---

Matchers can be arbitrarily chained and nested.

```php
$m = Matcher::multi('//div[@class="thread"]', [
  'op'      => './div[@class="postContainer opContainer"]',
  'replies' => Matcher::multi('./div[@class="postContainer replyContainer"]')
], Matcher::single('.//div[@class="postInfo desktop"]', [
  'id'   => './input/@name',
  'name' => './span[@class="nameBlock"]/span[@class="name"]',
  'date' => './span/@data-utc',
)])->fromHtml();

$f = file_get_contents('http://boards.4chan.org/po/');

$extractedData = $m($f);
```

result:

```php

[
  [
    "op" => [
      "id"   => "481874858",
      "name" => "Anonymous",
      "date" => "1369242761"
    ],
    "replies" => [
      [
        "id"   => "481879323",
        "name" => "WT Snacks",
        "date" => "1369244544"
      ],
      [
        "id"   => "481879347",
        "name" => "moot",
        "date" => "1369244554"
      ]
    ]
  ],
  [
    ...
  ],
  ...
]
```
