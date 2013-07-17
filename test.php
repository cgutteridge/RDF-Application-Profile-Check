#!/usr/bin/php
<?php
require_once( "../arc2-fork/ARC2.php" );
require_once( "../Graphite/Graphite.php" );

$doc_url = "soton-opd.ttl";
$profile_url = "opd-profile.ttl";

$doc_g = new Graphite();
$doc_g->setDebug( true );

$doc_ttl = file_get_contents( $doc_url );
$n = $doc_g->addTurtle( $doc_url, $doc_ttl );
print "Doc N Triples= $n\n";

$ap_g = new Graphite();
$ap_g->setDebug( true );
$profile_ttl = file_get_contents( $profile_url );
$ap_g->ns( "dsp","http://purl.org/dc/dsp/" );
$n = $ap_g->addTurtle( $profile_url, $profile_ttl );
print "Profile N Triples= $n\n";

# TODO: check each desc-template is only applied once per URI
# TODO: make $resource->all() do de-dup. (make default to do this, but option to disable for speed)
# TODO: graphite warn on namespaces which don't end with / or # 
# TODO: checker warn on namespaces which don't end with / or # 
# TODO: add $resList->dumpText(). COmmit from local copy

# for each non-standalone description template in the profile, 
  # collect resources which match the class
  # check numbers of resources which match against max/min
  # for all resources which match
    # check constraints
    # check this template has not already been applied to this resource
    # report 
    # apply X
foreach( $ap_g->allOfType( "dsp:DescriptionTemplate" ) as $description_t )
{
	if( $description_t->has( "dsp:standalone" ) 
	 && $description_t->getString( "dsp:standalone" ) == "false" ) { continue; }
	print "//start of loop\n";
	print "standalone!\n";
	print "DESCR:\n". $description_t->dumpText();

	if( !$description_t->has( "dsp:resourceClass" ) )
	{
		ap_error( $description_t->prettyLink()." has no dsp:resourceClass" );
	}

	$classes = $description_t->all( "dsp:resourceClass" );
	print "CLASSES:\n". $classes->dumpText();
#$classes = $doc_g->resource( "foaf:Organization" );
print "*******\n";
	$matches = $doc_g->allOfType( "foaf:Organization" );
	print "MATCHES:\n". $matches->dumpText();

	if( $description_t->has( "dsp:minOccur" ) && sizeof( $matches ) < $description_t->getString( "dsp:minOccur" ) )
	{
		ap_error( $description_t->prettyLink()." -- document has ".(sizeof($matches))." but should have at least ".$description_t->getString( "dsp:minOccur" )." and at most ".$description_t->getString( "dsp:maxOccur" ));
	}

	if( $description_t->has( "dsp:maxOccur" ) && sizeof( $matches ) > $description_t->getString( "dsp:maxOccur" ) )
	{
		ap_error( $description_t->prettyLink()." -- document has ".(sizeof($matches))." but should have at least ".$description_t->getString( "dsp:minOccur" )." and at most ".$description_t->getString( "dsp:maxOccur" ) );
	}
	print "//end of loop\n";
}
exit;

function ap_error( $msg )
{
	print "\n";
	print "<h1>Error in Application Profile</h1>\n";
	print "<p>$msg</p>\n";
	exit;
}

function doc_error( $msg )
{
	print "\n";
	print "<h1>Error in Document</h1>\n";
	print "<p>$msg</p>\n";
	exit;
}


#X:
  # report 
  # foreach statement templates & inverse statement template
    # check constraints
    # report
    # foreach literal constraint
      # check constraints
      # report
    # foreach non-literal constraint
      # check constraints
      # report
      # foreach description profile
        # check this template has not already been applied to this resource
        # X 
# unused triples report?
