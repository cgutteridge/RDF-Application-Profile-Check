#!/usr/bin/php
<?php
require_once( "arc/ARC2.php" );
require_once( "Graphite/Graphite.php" );

$doc_url = "soton-opd.ttl";
$profile_url = "opd-profile.ttl";

$ap = new AppProfile( $profile_url, new AppProfileIOCmdLine() );
$ap->applyTemplates( $doc_url );
exit;

class AppProfileIOHTML
{
	function apError( $msg )
	{
		print "<h1>Error in Application Profile</h1>\n";
		print "<p>$msg</p>\n";
	}

	function docError( $msg )
	{
		print "<h1>Error in Document</h1>\n";
		print "<p>$msg</p>\n";
	}

	function message( $msg )
	{
		print "<p>$msg</p>\n";
	}

	function prettyLink( $resource )
	{
		return $resource->prettyLink();
	}

	function incDepth()
	{
		print "<div style='margin-left: 2em'>";
	}

	function decDepth()
	{
		print "</div>";
	}
}
class AppProfileIOCmdLine
{
	var $depth = 0;
	function apError( $msg )
	{
		$this->message( "ERROR (PROFILE): $msg" );
	}

	function docError( $msg )
	{
		$this->message( "ERROR (DOCUMENT): $msg" );
	}

	function message( $msg )
	{
		if( $this->depth ) { for( $i=0;$i<=$this->depth;++$i ) { print "  "; } }
		print "* $msg\n";
	}

	function prettyLink( $resource )
	{
		if( $resource->hasLabel() )
		{
			return "\"".$resource->label()."\"";
		}
		return "<". $resource->toString().">";
	}

	function incDepth() { $this->depth++; }
	function decDepth() { $this->depth--; }
}

class AppProfile {

var $ap_g;
var $io;
function __construct( $profile_url, $io=null )
{
	if( $io === null ) { $io = new AppProfileIOCmdLine(); }
	$this->io = $io;
	$this->ap_g = new Graphite();
	$this->ap_g->setDebug( true );

	$this->io->message( "Loading profile: $profile_url" );
	$profile_ttl = file_get_contents( $profile_url );
	$this->ap_g->ns( "dsp", "http://purl.org/dc/dsp/" );
	$n = $this->ap_g->addTurtle( $profile_url, $profile_ttl );
	$this->io->message( "Profile N Triples= $n" );
}

function applyTemplates( $doc_url )
{
	$doc_g = new Graphite();
	$doc_g->setDebug( true );
	
	$this->io->message( "Loading document: $doc_url" );
	$doc_ttl = file_get_contents( $doc_url );
	$n = $doc_g->addTurtle( $doc_url, $doc_ttl );
	$this->io->message( "Doc N Triples= $n" );

# TODO: check each desc-template is only applied once per URI
# TODO: make $resource->all() do de-dup. (make default to do this, but option to disable for speed)
# TODO: graphite warn on namespaces which don't end with / or # 
# TODO: checker warn on namespaces which don't end with / or # 

# for each non-standalone description template in the profile, 
  # collect resources which match the class
  # check numbers of resources which match against max/min
  # for all resources which match
    # check constraints
    # check this template has not already been applied to this resource
    	# report 
    # apply X
	$standAloneTemplateCount = 0;

	foreach( $this->ap_g->allOfType( "dsp:DescriptionTemplate" ) as $description_t )
	{
		if( $description_t->has( "dsp:standalone" ) 
	 	&& $description_t->getString( "dsp:standalone" ) == "false" ) { continue; }
		
		$standAloneTemplateCount++;

		$desc = $this->io->prettyLink( $description_t );
		$min_max = $this->describeMinMax( $description_t );
	
		if( !$description_t->has( "dsp:resourceClass" ) )
		{
			$this->io->apError( $desc." has no dsp:resourceClass" );
			continue;
		}
		$classes = $description_t->all( "dsp:resourceClass" );
		if( sizeof($classes) == 1 )
		{
			$class_desc = $this->io->prettyLink( $classes[0] );
		}
		else
		{
			$a = array();
			foreach( $class_desc as $class ) 
			{
				$a[]= $this->io->prettyLink( $class );
			}
			$class_desc = "( ".join( ", ", $a )." )";
		}
		$this->io->message( "Applying stand-alone template $desc which requires $min_max instance(s) of class(es) ".$class_desc." in the target document." );
		$this->io->incDepth();
	
		$matches = new Graphite_ResourceList($doc_g);
		foreach( $classes as $class )
		{
			$more_matches = $doc_g->allOfType( $class->toString() );
			$matches = $matches->append( $more_matches );
		}
		$matches = $matches->distinct();
	
		if( $description_t->has( "dsp:minOccur" ) && sizeof( $matches ) < $description_t->getString( "dsp:minOccur" ) )
		{
			$this->io->docError( "document has ".(sizeof($matches))." matching instance(s) but should have $min_max" );
		}

		elseif( $description_t->has( "dsp:maxOccur" ) && sizeof( $matches ) > $description_t->getString( "dsp:maxOccur" ) )
		{
			$this->io->docError( "document has ".(sizeof($matches))." matching instance(s) but should have $min_max" );
		}
		else
		{
			$this->io->message( "document has ".(sizeof($matches))." matching instance(s) which is legit.");
		}

		foreach( $matches as $match )
		{
			$this->applyClassTemplate( $description_t, $match );
		}

		$this->io->decDepth();
	}

	if( $standAloneTemplateCount == 0 )
	{
		$this->io->apError( "Did not find any standalone templates." );
	}
	else
	{
		$this->io->message( "Applied $standAloneTemplateCount stand alone (top level) template(s)." );
	}
}


#apply-template:
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

function applyClassTemplate( $template, $resource )
{
	$t_desc = $this->io->prettyLink( $template );
	$r_desc = $this->io->prettyLink( $resource );
	$this->io->message( "Applying class template $t_desc to resource $r_desc" );
	$this->io->incDepth();
	
	foreach( $template->all( "dsp:statementTemplate" ) as $s_template )
	{
		print "\nStatementTemplate...\n";
		print $s_template->dumpText()."\n";
		if( $s_template->isType( "dsp:nonLiteralStatementTemplate" ) )
		{
			$this->applyNonLiteralStatementTemplate( $s_template, $resource );
		}
		elseif( $s_template->isType( "dsp:literalStatementTemplate" ) )
		{
			#TODO 
			$this->applyLiteralStatementTemplate( $s_template, $resource );
		}
		elseif( $s_template->isType( "dsp:inverseStatementTemplate" ) )
		{
			#TODO 
			$this->applyInverseStatementTemplate( $s_template, $resource );
		}
		else
		{
			$this->io->apError( "Unknown template type for ".$this->io->prettyLink( $s_template ).": ".$this->io->prettyLink( $s_template->get( "rdf:type" ) )."." );
		}
	}
	$this->io->decDepth();
}

function applyNonLiteralStatementTemplate( $template, $resource )
{

	if( $template->has( "dsp:property" ) && $template->has( "dsp:subPropertyOf" ) )
	{
		$this->io->apError( "A statement template should not really have both 'property' and 'subPropertyOf' set." );
	}

	if( !$template->has( "dsp:property" ) && !$template->has( "dsp:subPropertyOf" ) )
	{
		$this->io->apError( "A statement template should have either one or more 'property' or a single 'subPropertyOf'." );
		return;
	}

	if( $template->has( "dsp:property" ) )
	{
		$properties = $template->all( "dsp:property" );
	}
	else
	{
		$super_prop = $template->get( "dsp:subPropertyOf" );

		# only bothers going two levels of sub property
		$properties = $super_prop->all( "-rdfs:subPropertyOf" )->all( "-rdfs:subPropertyOf" );
		$properties = $properties->append( $super_prop->all( "-rdfs:subPropertyOf" ) );
		# don't forget the top level property
		$properties = $properties->append( $super_prop );
	}

	$propertyMatches = $resource->all( $properties )->distinct();
	
	if( $template->has( "dsp:minOccur" ) && sizeof( $propertyMatches ) < $template->getString( "dsp:minOccur" ) )
	{
		$this->io->docError( "resource has ".(sizeof($propertyMatches))." matching property(s) but should have $min_max" );
	}
	elseif( $template->has( "dsp:maxOccur" ) && sizeof( $propertyMatches ) > $template->getString( "dsp:maxOccur" ) )
	{
		$this->io->docError( "resource has ".(sizeof($propertyMatches))." matching property(s) but should have $min_max" );
	}
	else
	{
		$this->io->message( "resource has ".(sizeof($propertyMatches))." matching property(s) which is legit.");
	}


}

// return a string describing the minimum and maximum occurances allowed
// assumes minOccur or maxOccur is set.
function describeMinMax( $description_t )
{
	if( $description_t->has( "dsp:minOccur" ) && !$description_t->has( "dsp:maxOccur" ) )
	{
		return "at least ".$description_t->getString( "dsp:minOccur" );
	}

	if( !$description_t->has( "dsp:minOccur" ) && $description_t->has( "dsp:maxOccur" ) )
	{
		return "at most ".$description_t->getString( "dsp:minOccur" );
	}

	$min = $description_t->getString( "dsp:minOccur" );	
	$max = $description_t->getString( "dsp:maxOccur" );
	if( $min == $max )
	{
		return "exactly $min";
	}
			
	return "at least $min and at most $max";
}

} // end of class AppProfile
