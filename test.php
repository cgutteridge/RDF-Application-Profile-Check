#!/usr/bin/php
<?php
require_once( "../arc2-fork/ARC2.php" );
require_once( "../Graphite/graphite/Graphite.php" );

$doc_url = "soton-opd.ttl";
$profile_url = "opd-profile.ttl";

$graph = new Graphite();
$graph->setDebug( true );

$doc_ttl = file_get_contents( $doc_url );
$n = $graph->addTurtle( $doc_url, $doc_ttl );
print "Doc N Triples= $n\n";

$profile_ttl = file_get_contents( "profile.url" );
$n = $graph->addTurtle( $profile_url, $profile_ttl );
print "Profile N Triples= $n\n";

