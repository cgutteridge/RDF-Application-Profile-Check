#!/usr/bin/php
<?php
require_once( "../arc2-fork/ARC2.php" );
require_once( "../Graphite/graphite/Graphite.php" );

$graph = new Graphite();
$graph->setDebug( true );

$profile_ttl = file_get_contents( "profile.ttl" );
print $profile_ttl."\n";
$n = $graph->addTurtle( "http://id.southampton.ac.uk/", $profile_ttl );

print "N Triples= $n\n";
print $graph->dumpText();
