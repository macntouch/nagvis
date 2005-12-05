<?
##########################################################################
##     	                           NagVis                              ##
##                                                                      ##
##                      *** NagVis Startseite ***                       ##
##                                                                      ##
##      Autor von NagVis 0.0.3: J�rg Linge <pichfork@ederdrom.de>       ##
##                                                                      ##
##         Umgschrieben von Perl in PHP und weiterentwickelt by         ##
##                      Michael L�bben(MiCkEy2002)                      ##
##                                                                      ##
##                               Lizenz GPL                             ##
##########################################################################

include("./etc/config.inc.php");
include("./includes/classes/graphic.php");
include("./includes/classes/class.CheckIt.php");
include("./includes/classes/class.readFiles.php");
include("./includes/classes/class.checkState.php");

$checkit = new checkit();

// Browser ermitteln.
unset($browser);
$browser = $_SERVER['HTTP_USER_AGENT'];

// Map festlegen.
if(isset($_GET['map'])) {
    $map = $_GET['map'];
} else {
    $map = $maps[0];
}

$nagvis = new NagVis();
$readfile = new readFile();
$rotateUrl = "";

// check-stuff
$checkit->check_user();
$checkit->check_gd();
$checkit->check_cgipath();
$checkit->check_wuibash();

// Pr�fen ob Rotate-Modus eingeschaltet ist.
if($RotateMaps == "1") {
    $mapNumber = $nagvis->mapCount($map);
    $map = $maps[$mapNumber];
    $rotateUrl = " URL=index.php?map=".$map;
}  

//Pr�fen ob *.cfg-Datei vorhanden ist und dann einlesen.
//FIXME sollte erst NACH den ganzen Plausis gemacht werden
if(file_exists($cfgFolder.$map.".cfg")) {
    $mapCfg = $readfile->readNagVisCfg($map);
    $allowed_users = explode(",",trim($mapCfg[1]['allowed_user']));
    $map_image_array = explode(",",trim($mapCfg[1]['map_image']));
    $map_image=$map_image_array[0];
}
$nagvis->openSite($rotateUrl);

$checkit->check_map_isreadable();
$checkit->check_mapimg();
$checkit->check_permissions();

// Pr�fen ob Header eingeschaltet ist und bei bedarf erzeugen.
if ($Header == "1") {
	$Menu = $readfile->readMenu();
	$nagvis->makeHeaderMenu($Menu);
}

//Map als Hintergrundbild erzeugen
$nagvis->printMap($map);

$checkstate = new checkState();
$countStates = count($mapCfg)-1;
$arrayPos="2";
for($x="1";$x<=$countStates;$x++) { 
    if(!isset($mapCfg[$arrayPos]['recognize_services'])) {
	$mapCfg[$arrayPos]['recognize_services'] = 1;
    }
    if(!isset($mapCfg[$arrayPos]['service_description'])) {
	$mapCfg[$arrayPos]['service_description'] = "";
    }
    // Links zu Nagios CGI�s
    if(isset($mapCfg[$arrayPos]['url'])) {
	$link = '<A HREF='.$mapCfg[$arrayPos]['url'].'>';
    } elseif($mapCfg[$arrayPos]['type'] == 'host') {
    $link = '<A HREF="'.$HTMLCgiPath.'/status.cgi?host='.$mapCfg[$arrayPos]['name'].'">';
    } elseif($mapCfg[$arrayPos]['type'] == 'service') {
	$link = '<A HREF="'.$HTMLCgiPath.'/extinfo.cgi?type=2&host='.$mapCfg[$arrayPos]['name'].'&service='.$mapCfg[$arrayPos]['service_description'].'">';
    } elseif($mapCfg[$arrayPos]['type'] == 'hostgroup') {
	$link = '<A HREF="'.$HTMLCgiPath.'/status.cgi?hostgroup='.$mapCfg[$arrayPos]['name'].'&style=detail">';
    } elseif($mapCfg[$arrayPos]['type'] == 'servicegroup') {
	$link = '<A HREF="'.$HTMLCgiPath.'/status.cgi?servicegroup='.$mapCfg[$arrayPos]['name'].'&style=detail">';
    }
    //Status einer Map ermitteln
    //Fixed by mluebben
    if($mapCfg[$arrayPos]['type'] == 'map') {
	if(file_exists($cfgFolder.$mapCfg[$arrayPos]['name'].'.cfg')) {
	    $allMapCfgState = $readfile->readNagVisCfg($mapCfg[$arrayPos]['name']);
		
		/*
		    $countStatesState = count($mapCfgState);
		    if(!isset($mapCfgState['recognize_services'])) {
			$mapCfgState['recognize_services'] = 1;
		    }
		    if(!isset($mapCfgState[$arrayPos]['service_description'])) {
			$mapCfgState[$arrayPos]['service_description'] = "";
		    }
		*/

	    foreach ($allMapCfgState as $mapCfgState) {

	    if(!isset($mapCfgState['recognize_services'])) {
		$mapCfgState['recognize_services'] = 1;
	    }

		$allowed_types = array(host,service,hostgroup,servicegroup);
		if(in_array($mapCfgState['type'], $allowed_types)){
		    $stateState = $checkstate->checkStates($mapCfgState['type'],$mapCfgState['name'],$mapCfgState['recognize_services'],$mapCfgState['service_description'],0,$CgiPath,$CgiUser);

		    $mapState[] = $stateState['State'];
    
		}
		$countStatesState++;
	    }
	    if(in_array("DOWN", $mapState) || in_array("CRITICAL", $mapState)){
		$state['State'] = "CRITICAL";
		$state['Output'] = "State of parent Map is CRITICAL or DOWN";
		$Icon = $checkstate->fixIcon($state,$mapCfg,$arrayPos,$defaultIcons);

	    }elseif(in_array("WARNING", $mapState)){
		$state['State'] = "WARNING";
		$state['Output'] = "State of parent Map is  WARNING";
		$Icon = $checkstate->fixIcon($state,$mapCfg,$arrayPos,$defaultIcons);
	    }else{
		$state['State'] = "OK";
		$state['Output'] = "State of parent map is OK or UP";
		$Icon = $checkstate->fixIcon($state,$mapCfg,$arrayPos,$defaultIcons);
	    }
	} else {
	    $state['State'] = "OK";
	    $state['Output'] = "Map not readable";
	}
	$IconPosition = $checkstate->fixIconPosition($Icon,$mapCfg[$arrayPos]['x'],$mapCfg[$arrayPos]['y']);
	$nagvis->site[] = $IconPosition;
	$Box = $nagvis->infoBox($mapCfg[$arrayPos]['type'],$mapCfg[$arrayPos]['name'],$mapCfg[$arrayPos]['service_description'],$state);
	$nagvis->site[] = '<A HREF="./index.php?map='.$mapCfg[$arrayPos]['name'].'" TARGET="_self"><IMG SRC='.$iconHTMLBaseFolder.$Icon.' '.$Box.'; BORDER="0"></A>';
	$nagvis->site[] = "</DIV>";
    
    }elseif($mapCfg[$arrayPos]['type'] == 'textbox') {
	// Textboxen
	$TextBox = $checkstate->TextBox($mapCfg[$arrayPos]['x'],$mapCfg[$arrayPos]['y'],$mapCfg[$arrayPos]['w'],$mapCfg[$arrayPos]['text']);
	$nagvis->site[] = $TextBox;		
	}elseif(isset($mapCfg[$arrayPos]['line_type'])) {
	    if($mapCfg[$arrayPos]['line_type'] == '10' || $mapCfg[$arrayPos]['line_type'] == '11'){
		$state = $checkstate->checkStates($mapCfg[$arrayPos]['type'],$mapCfg[$arrayPos]['name'],$mapCfg[$arrayPos]['recognize_services'],$mapCfg[$arrayPos]['service_description'],0,$CgiPath,$CgiUser);
		list($x_from,$x_to) = explode(",", $mapCfg[$arrayPos]['x']);
		list($y_from,$y_to) = explode(",", $mapCfg[$arrayPos]['y']);
		$x_middle = middle($x_from,$x_to);
		$y_middle = middle($y_from,$y_to);
		$IconPosition = $checkstate->fixIconPosition('20x20.gif',$x_middle,$y_middle);
		$Box = $nagvis->infoBox($mapCfg[$arrayPos]['type'],$mapCfg[$arrayPos]['name'],$mapCfg[$arrayPos]['service_description'],$state);
		$nagvis->site[] = $IconPosition;
		$nagvis->site[] = $link;
		$nagvis->site[] = '<img src=iconsets/20x20.gif '.$Box.';></A>';
		$nagvis->site[] = '</div>';

	    } elseif($mapCfg[$arrayPos]['line_type'] == '20'){
		list($host_name_from,$host_name_to) = explode(",", $mapCfg[$arrayPos]['name']);
		list($service_description_from,$service_description_to) = explode(",", $mapCfg[$arrayPos]['service_description']);
		$state_from = $checkstate->checkStates($mapCfg[$arrayPos]['type'],$host_name_from,$mapCfg[$arrayPos]['recognize_services'],$service_description_from,1,$CgiPath,$CgiUser);
		$state_to = $checkstate->checkStates($mapCfg[$arrayPos]['type'],$host_name_to,$mapCfg[$arrayPos]['recognize_services'],$service_description_to,2,$CgiPath,$CgiUser);

		list($x_from,$x_to) = explode(",", $mapCfg[$arrayPos]['x']);
		list($y_from,$y_to) = explode(",", $mapCfg[$arrayPos]['y']);
		// From
		$x_middle = middle2($x_from,$x_to);
		$y_middle = middle2($y_from,$y_to);
		$IconPosition = $checkstate->fixIconPosition('20x20.gif',$x_middle,$y_middle);
		$Box = $nagvis->infoBox($mapCfg[$arrayPos]['type'],$host_name_from,$service_description_from,$state_from);
		$nagvis->site[] = $IconPosition;
		$nagvis->site[] = $link;
		$nagvis->site[] = '<img src= iconsets/20x20.gif '.$Box.';></A>';
		$nagvis->site[] = '</div>';
		// To
		$x_middle = middle2($x_to,$x_from);
		$y_middle = middle2($y_to,$y_from);
		$IconPosition = $checkstate->fixIconPosition('20x20.gif',$x_middle,$y_middle);
		$Box = $nagvis->infoBox($mapCfg[$arrayPos]['type'],$host_name_to,$service_description_to,$state_to);
		$nagvis->site[] = $IconPosition;
		$nagvis->site[] = $link;
		$nagvis->site[] = '<img src=iconsets/20x20.gif '.$Box.';></A>';
		$nagvis->site[] = '</div>';
		}	

	    }elseif(!isset($mapCfg[$arrayPos]['line_type'])) {
		
		$state = $checkstate->checkStates($mapCfg[$arrayPos]['type'],$mapCfg[$arrayPos]['name'],$mapCfg[$arrayPos]['recognize_services'],$mapCfg[$arrayPos]['service_description'],0,$CgiPath,$CgiUser);

		$Icon = $checkstate->fixIcon($state,$mapCfg,$arrayPos,$defaultIcons);
		$IconPosition = $checkstate->fixIconPosition($Icon,$mapCfg[$arrayPos]['x'],$mapCfg[$arrayPos]['y']);
		$nagvis->site[] = $IconPosition;
		
		$Box = $nagvis->infoBox($mapCfg[$arrayPos]['type'],$mapCfg[$arrayPos]['name'],$mapCfg[$arrayPos]['service_description'],$state);
		$nagvis->site[] = $link;
		$nagvis->site[] = '<IMG SRC='.$iconHTMLBaseFolder.$Icon.' '.$Box.';></A>';
		
		$nagvis->site[] = "</DIV>";
	    }
    $arrayPos++;
}
$nagvis->closeSite();
$nagvis->printSite();

?>
