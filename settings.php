<?php

$db=new DB\SQL(
    'mysql:host=localhost;port=3306;dbname=fastvps',
    'fastvps',
    'W8bP7NS2Xbj7X338'
);

$f3->set('CACHE',TRUE);
$f3->set('CACHE','memcached=localhost:11211');