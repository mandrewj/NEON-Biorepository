<?php
include_once('../../config/symbini.php');
include_once($SERVER_ROOT.'/classes/DwcArchiverPublisher.php');
include_once($SERVER_ROOT.'/classes/OccurrenceCollectionProfile.php');
header('Content-Type: text/html; charset=' .$CHARSET);

$collid = array_key_exists('collid',$_REQUEST)?$_REQUEST['collid']:0;
$emode = array_key_exists('emode',$_REQUEST)?$_REQUEST['emode']:0;
$action = array_key_exists('formsubmit',$_REQUEST)?$_REQUEST['formsubmit']:'';

if(!is_numeric($collid)) $collid = 0;
if(!is_numeric($emode)) $emode = 0;

$dwcaManager = new DwcArchiverPublisher();
$collManager = new OccurrenceCollectionProfile();

$publishGBIF = false;
$collArr = array();
if($collid){
	$collManager->setCollid($collid);
	$dwcaManager->setCollArr($collid);
	$collArr = current($collManager->getCollectionMetadata());
	if($collArr['publishtogbif']) $publishGBIF = true;
}

$includeDets = 1;
$includeImgs = 1;
$redactLocalities = 1;
if($action == 'Save Key' || $action == 'Update Key' || (isset($_REQUEST['datasetKey']) && $_REQUEST['datasetKey'])){
	$collManager->setAggKeys($_POST);
	$collManager->updateAggKeys();
}
elseif($action){
	if (!array_key_exists('dets', $_POST)) {
		$includeDets = 0;
		$dwcaManager->setIncludeDets(0);
	}
	if (!array_key_exists('imgs', $_POST)) {
		$includeImgs = 0;
		$dwcaManager->setIncludeImgs(0);
	}
	if (!array_key_exists('redact', $_POST)) {
		$redactLocalities = 0;
		$dwcaManager->setRedactLocalities(0);
	}
	$dwcaManager->setTargetPath($SERVER_ROOT . (substr($SERVER_ROOT, -1) == '/' ? '' : '/') . 'content/dwca/');
}

$idigbioKey = $collManager->getIdigbioKey();

$isEditor = 0;
if($IS_ADMIN || (array_key_exists('CollAdmin',$USER_RIGHTS) && in_array($collid,$USER_RIGHTS['CollAdmin']))){
	$isEditor = 1;
}

if($isEditor){
	if(array_key_exists('colliddel',$_POST)){
		$dwcaManager->deleteArchive($_POST['colliddel']);
	}
}
?>
<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=<?php echo $CHARSET; ?>">
	<title>Darwin Core Archiver Publisher</title>
	<link href="../../css/base.css?ver=<?php echo $CSS_VERSION; ?>" type="text/css" rel="stylesheet" />
	<link href="../../css/main.css<?php echo (isset($CSS_VERSION_LOCAL)?'?ver='.$CSS_VERSION_LOCAL:''); ?>" type="text/css" rel="stylesheet">
	<link href="../../css/jquery-ui.css" type="text/css" rel="Stylesheet" />
	<style type="text/css">
		.nowrap { white-space: nowrap; }
	</style>
	<script type="text/javascript" src="../../js/jquery.js"></script>
	<script type="text/javascript" src="../../js/jquery-ui.js"></script>
	<script type="text/javascript" src="../../js/symb/collections.gbifpublisher.js?ver=4"></script>
	<script type="text/javascript">
		function toggle(target){
			var objDiv = document.getElementById(target);
			if(objDiv){
				if(objDiv.style.display=="none"){
					objDiv.style.display = "block";
				}
				else{
					objDiv.style.display = "none";
				}
			}
			else{
			  	var divs = document.getElementsByTagName("div");
			  	for (var h = 0; h < divs.length; h++) {
			  	var divObj = divs[h];
					if(divObj.className == target){
						if(divObj.style.display=="none"){
							divObj.style.display="block";
						}
					 	else {
					 		divObj.style.display="none";
					 	}
					}
				}
			}
			return false;
		}

		function verifyDwcaForm(f){

			return true;
		}

		function verifyDwcaAdminForm(f){
			var dbElements = document.getElementsByName("coll[]");
			for(i = 0; i < dbElements.length; i++){
				var dbElement = dbElements[i];
				if(dbElement.checked) return true;
			}
		   	alert("Please choose at least one collection!");
			return false;
		}

		function verifyGbifForm(f){
			if(f.organizationKey.value == ""){
				alert("Please enter GBIF key");
				return false;
			}
			return true;
		}

		function validateKey(f){
			var keyValue = f.organizationKey.value;
			if(keyValue.indexOf("/")) keyValue = keyValue.substring(keyValue.lastIndexOf("/")+1);
			f.organizationKey.value = keyValue;
			if(keyValue.length != 36){
				alert("Key is the wrong number of digits. Should be 36 digits in total.");
				return false;
			}
			if((keyValue.substring(8,9) != "-") || keyValue.substring(13,14) != "-" || keyValue.substring(18,19) != "-" || keyValue.substring(23,24) != "-"){
				alert("Key does not appear to be a valid UUID (e.g. 7a989612-d0ff-407a-8aba-0a6d06f58dca)");
				return false;
			}
			getOrganization(f);
			return true;
		}

		function checkAllColl(cb){
			var boxesChecked = true;
			if(!cb.checked){
				boxesChecked = false;
			}
			var cName = cb.className;
			var dbElements = document.getElementsByName("coll[]");
			for(i = 0; i < dbElements.length; i++){
				var dbElement = dbElements[i];
				if(dbElement.className == cName){
					if(dbElement.disabled == false) dbElement.checked = boxesChecked;
				}
				else{
					dbElement.checked = false;
				}
			}
		}
	</script>
</head>
<body>
<?php
$displayLeftMenu = (isset($collections_datasets_datapublisherMenu)?$collections_datasets_datapublisherMenu: 'true');
include($SERVER_ROOT. '/header.php');
?>
<div class='navpath'>
	<a href="../../index.php">Home</a> &gt;&gt;
	<?php
	if($collid){
		?>
		<a href="../misc/collprofiles.php?collid=<?php echo $collid; ?>&emode=1">Collection Management</a> &gt;&gt;
		<?php
	}
	else{
		?>
		<a href="../../sitemap.php">Sitemap</a> &gt;&gt;
		<?php
	}
	?>
	<b>Darwin Core Archive Publisher</b>
</div>
<!-- This is inner text! -->
<div id="innertext">
	<?php
	if(!$collid && $IS_ADMIN){
		?>
		<div style="float:right;">
			<a href="#" title="Display Publishing Control Panel" onclick="toggle('dwcaadmindiv')">
				<img style="border:0;width:12px;" src="../../images/edit.png" />
			</a>
		</div>
		<?php
	}
	?>
	<h1>Darwin Core Archive Publishing</h1>
	<?php
	if($collid){
		echo '<div style="font-weight:bold;font-size:120%;">'.$collArr['collectionname'].'</div>';
		?>
		<div style="margin:10px;">
			Use the controls below to publish occurrence data within this collection as a
			<a href="http://rs.tdwg.org/dwc/terms/guides/text/index.htm">Darwin Core Archive (DwC-A)</a> file.
			A DwC-A file is a single compressed ZIP file that contains one to several data files along with a meta.xml
			document that describes the content.
			The occurrence data file is required, but identifications (determinations) and image metadata are optional.
			Fields within the occurrences.csv file are defined by the <a href="http://rs.tdwg.org/dwc/terms/index.htm">Darwin Core</a>
			exchange standard.
		</div>
		<?php
	}
	else{
		?>
		<div style="margin:10px;">
			The following downloads are occurrence data packages from collections
			that have chosen to publish their complete dataset as a
			<a href="http://rs.tdwg.org/dwc/terms/guides/text/index.htm">Darwin Core Archive (DwC-A)</a> file.
			A DwC-A file is a single compressed ZIP file that contains one to several data files along with a meta.xml
			document that describes the content.
			The archives below contain three comma separated (CSV) files containing occurrences, identifications (determinations), and image metadata.
			Fields within the occurrences.csv file are defined by the <a href="http://rs.tdwg.org/dwc/terms/index.htm">Darwin Core</a>
			exchange standard. The identification and image files follow the DwC extensions for those data types.
		</div>
		<div style="margin:10px;">
			<h3>Data Usage Policy:</h3>
			Use of these datasets requires agreement with the terms and conditions in our
			<a href="../../misc/usagepolicy.php">Data Usage Policy</a>.
			Locality details for rare, threatened, or sensitive records have been redacted from these data files.
			One must contact the collections directly to obtain access to sensitive locality data.
		</div>
		<?php
	}
	?>
	<div style="margin:20px;">
		<b>RSS Feed:</b>
		<?php
		$urlPrefix = $dwcaManager->getServerDomain().$CLIENT_ROOT.(substr($CLIENT_ROOT,-1)=='/'?'':'/');
		if(file_exists('../../webservices/dwc/rss.xml')){
			$feedLink = $urlPrefix.'webservices/dwc/rss.xml';
			echo '<a href="'.$feedLink.'" target="_blank">'.$feedLink.'</a>';
		}
		else{
			echo '--feed not published for any of the collections within the portal--';
		}
		?>
	</div>
	<?php
	if($collid){
		if($action == 'Create/Refresh Darwin Core Archive'){
			echo '<ul>';
			$dwcaManager->setVerboseMode(3);
			$dwcaManager->setLimitToGuids(true);
			$dwcaManager->createDwcArchive();
			$dwcaManager->writeRssFile();
			echo '</ul>';
			if($publishGBIF && $collManager->getEndpointKey()){
				$collManager->triggerGBIFCrawl($collManager->getDatasetKey());
			}
		}
		$dwcUri = '';
		$dwcaArr = $dwcaManager->getDwcaItems($collid);
		if($dwcaArr){
			$dArr = current($dwcaArr);
			$dwcUri = ($dArr['collid'] == $collid?$dArr['link']:'');
			if(!$idigbioKey) $idigbioKey = $collManager->findIdigbioKey($collArr['guid']);
			?>
			<div style="margin:10px;">
				<div>
					<b>Title:</b> <?php echo $dArr['title']; ?>
					<form action="datapublisher.php" method="post" style="display:inline;" onsubmit="return window.confirm('Are you sure you want to delete this archive?');">
						<input type="hidden" name="colliddel" value="<?php echo $dArr['collid']; ?>">
						<input type="hidden" name="collid" value="<?php echo $dArr['collid']; ?>">
						<input type="image" src="../../images/del.png" name="action" value="DeleteCollid" title="Delete Archive" style="width:15px;" />
					</form>
				</div>
				<div><b>Description:</b> <?php echo $dArr['description']; ?></div>
				<?php
				$emlLink = $urlPrefix.'collections/datasets/emlhandler.php?collid='.$collid;
				?>
				<div><b>EML:</b> <a href="<?php echo $emlLink; ?>"><?php echo $emlLink; ?></a></div>
				<div><b>DwC-Archive File:</b> <a href="<?php echo $dArr['link']; ?>"><?php echo $dArr['link']; ?></a></div>
				<div><b>Publication Date:</b> <?php echo $dArr['pubDate']; ?></div>
			</div>
			<?php
		}
		else{
			echo '<div style="margin:20px;font-weight:bold;color:orange;">A Darwin Core Archive has not yet been published for this collection</div>';
		}
		?>
		<fieldset style="margin:15px;padding:15px;">
			<legend><b>Publishing Information</b></legend>
			<?php
			//Data integrity checks
			$blockSubmitMsg = '';
			$recFlagArr = $dwcaManager->verifyCollRecords($collid);
			if($collArr['guidtarget']){
				echo '<div style="margin:10px;"><b>GUID source:</b> '.$collArr['guidtarget'].'</div>';
				if($recFlagArr['nullGUIDs']){
					echo '<div style="margin:10px;">';
					if($collArr['guidtarget'] == 'occurrenceId'){
						echo '<b>Records missing <a href="" target="_blank">OccurrenceID GUIDs</a>:</b> '.$recFlagArr['nullGUIDs'];
						echo ' <span style="color:red;margin-left:15px;">These records will not be published!</span> ';
					}
					elseif($collArr['guidtarget'] == 'catalogNumber'){
						echo '<b>Records missing Catalog Numbers:</b> '.$recFlagArr['nullGUIDs'];
						echo ' <span style="color:red;margin-left:15px;">These records will not be published!</span> ';
					}
					else{
						echo 'Records missing Symbiota GUIDs: '.$recFlagArr['nullGUIDs'].'<br/>';
						echo 'Please go to the <a href="../admin/guidmapper.php?collid='.$collid.'">Collection GUID Mapper</a> to assign Symbiota GUIDs.';
					}
					echo '</div>';
				}
				if($collArr['dwcaurl']){
					$serverName = $_SERVER["SERVER_NAME"];
					if(substr($serverName, 0, 4) == 'www.') $serverName = substr($serverName, 4);
					if(!strpos($collArr['dwcaurl'],$serverName)){
						$baseUrl = substr($collArr['dwcaurl'],0,strpos($collArr['dwcaurl'],'/content')).'/collections/datasets/datapublisher.php';
						$blockSubmitMsg = 'Already published on sister portal (<a href="'.$baseUrl.'" target="_blank">'.substr($baseUrl,0,strpos($baseUrl,'/',10)).'</a>) ';
					}
				}
			}
			else{
				echo '<div style="margin:10px;font-weight:bold;color:red;">The GUID source has not been set for this collection. Please go to the <a href="../misc/collmetadata.php?collid='.$collid.'">Edit Metadata page</a> to set GUID source.</div>';
				$blockSubmitMsg = 'Archive cannot be published until occurrenceID GUID source is set<br/>';
			}
			if($recFlagArr['nullBasisRec']){
				echo '<div style="margin:10px;font-weight:bold;color:red;">There are '.$recFlagArr['nullBasisRec'].' records missing basisOfRecord and will not be published. Please go to <a href="../editor/occurrencetabledisplay.php?q_recordedby=&q_recordnumber=&q_eventdate=&q_catalognumber=&q_othercatalognumbers=&q_observeruid=&q_recordenteredby=&q_dateentered=&q_datelastmodified=&q_processingstatus=&q_customfield1=basisOfRecord&q_customtype1=NULL&q_customvalue1=&q_customfield2=&q_customtype2=EQUALS&q_customvalue2=&q_customfield3=&q_customtype3=EQUALS&q_customvalue3=&collid='.$collid.'&csmode=0&occid=&occindex=0&orderby=&orderbydir=ASC">Edit Existing Occurrence Records</a> to correct this.</div>';
			}
			if($publishGBIF && $dwcUri && isset($GBIF_USERNAME) && isset($GBIF_PASSWORD) && isset($GBIF_ORG_KEY)){
				if($collManager->getDatasetKey()){
					$dataUrl = 'http://www.gbif.org/dataset/'.$collManager->getDatasetKey();
					?>
					<div style="margin:10px;">
						<div><b>GBIF Dataset page:</b> <a href="<?php echo $dataUrl; ?>" target="_blank"><?php echo $dataUrl; ?></a></div>
					</div>
					<?php
				}
				else{
					?>
					<div style="margin:10px;">
						You have selected for this collection's DwC archive data package to be published to GBIF. Please go to the
						<a href="https://www.gbif.org/become-a-publisher" target="_blank">GBIF Endorsement Request page</a> to
						register your institution with GBIF and enter the Publisher Key provided by GBIF below. If your institution already exists within the
						GBIF Organization lookup, a GBIF Publisher Key has already been assigned. The key is the remaining part of
						the URL after the last backslash of your institution's GBIF Data Provider page. If your data is already published in GBIF,
						DO NOT REPUBLISH without first contacting GBIF (<a href="mailto:helpdesk@gbif.org">helpdesk@gbif.org</a>) to coordinate data versions.
						<form style="margin-top:10px;" name="gbifpubform" action="datapublisher.php" method="post" onsubmit="return verifyGbifForm(this);">
							<b>GBIF Key:</b> <input type="text" id="organizationKey" name=organizationKey value="<?php echo $collManager->getOrganizationKey(); ?>" style="width:275px;" />
							<input type="hidden" name="collid" value="<?php echo $collid; ?>" />
							<input type="hidden" id="portalname" name="portalname" value="<?php echo $DEFAULT_TITLE; ?>" />
							<input type="hidden" id="collname" name="collname" value="<?php echo $collArr['collectionname']; ?>" />
							<input type="hidden" id="gbifInstOrgKey" name="gbifInstOrgKey" value="<?php echo $GBIF_ORG_KEY; ?>" />
							<input type="hidden" id="installationKey" name="installationKey" value="<?php echo $collManager->getInstallationKey(); ?>" />
							<input type="hidden" id="datasetKey" name="datasetKey" value="" />
							<input type="hidden" id="endpointKey" name="endpointKey" value="" />
							<input type="hidden" id="dwcUri" name="dwcUri" value="<?php echo $dwcUri; ?>" />
							<button type="button" onclick="validateKey(this.form)">Validate Key</button>
							<input type="submit" name="formsubmit" value="<?php echo ($collManager->getOrganizationKey()?'Update':'Save'); ?> Key" disabled />
							<span id="validKeyMsg" style="color:green;display:none">Key validated! Save key to proceed to next step.</span>
							<?php
							if($collManager->getOrganizationKey()){
								?>
								<div style="margin:10px 0px;clear:both;">
									<?php
									$collPath = "http://";
									if((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443) $collPath = "https://";
									$collPath .= $_SERVER["SERVER_NAME"];
									if($_SERVER["SERVER_PORT"] && $_SERVER["SERVER_PORT"] != 80) $collPath .= ':'.$_SERVER["SERVER_PORT"];
									$collPath .= $CLIENT_ROOT.'/collections/misc/collprofiles.php?collid='.$collid;
									$bodyStr = 'Please%20provide%20the%20following%20GBIF%20user%20permission%20to%20create%20and%20update%20datasets%20for%20the%20following%20GBIF%20publisher.%0A'.
										'Once%20these%20permissions%20are%20assigned,%20we%20will%20be%20pushing%20a%20DwC-Archive%20from%20the%20following%20Symbiota%20collection%20to%20GBIF.%0A%0A'.
										'GBIF%20user:%20'.$GBIF_USERNAME.'%0A'.
										'GBIF%20publisher%20identifier:%20'.$collManager->getOrganizationKey().'%0A'.
										'GBIF%20publisher:%20https://www.gbif.org/publisher/'.$collManager->getOrganizationKey().'%0A'.
										'Symbiota collection:%20'.$collPath.'%0A%0A'.
										'Sincerely, %0A%0A%0A%0A%0A%0A';
									?>
									Before submitting your data to GBIF, you will need to contact GBIF
									(<a href="mailto:helpdesk@gbif.org?subject=Publishing%20data%20from%20Symbiota%20portal%20to%20GBIF...&body=<?php echo $bodyStr; ?>">helpdesk@gbif.org</a>)
									requesting that the <b><?php echo $GBIF_USERNAME; ?></b> GBIF user is given permission to create and update datasets for your collection.
									Click on the email address above to automatically generate an email message within your email client.<br/><br/>
									<button type="button" onclick="processGbifOrgKey(this.form);">Submit Data</button>
									<img id="workingcircle" src="../../images/ajax-loader_sm.gif" style="margin-bottom:-4px;width:20px;display:none;" />
								</div>
								<?php
							}
							?>
						</form>
					</div>
					<?php
				}
			}
			if($idigbioKey && $dwcUri){
				$dataUrl = 'https://www.idigbio.org/portal/recordsets/'.$idigbioKey;
				?>
				<div style="margin:10px;">
					<div><b>iDigBio Dataset page:</b> <a href="<?php echo $dataUrl; ?>" target="_blank"><?php echo $dataUrl; ?></a></div>
				</div>
				<?php
			}
			?>
		</fieldset>
		<fieldset style="padding:15px;margin:15px;">
			<legend><b>Publish/Refresh DwC-A File</b></legend>
			<form name="dwcaform" action="datapublisher.php" method="post" onsubmit="return verifyDwcaForm(this)">
				<div>
					<input type="checkbox" name="dets" value="1" <?php echo ($includeDets?'CHECKED':''); ?> /> Include Determination History<br/>
					<input type="checkbox" name="imgs" value="1" <?php echo ($includeImgs?'CHECKED':''); ?> /> Include Image URLs<br/>
					<input type="checkbox" name="redact" value="1" <?php echo ($redactLocalities?'CHECKED':''); ?> /> Redact Sensitive Localities<br/>
				</div>
				<div style="clear:both;margin:10px;">
					<input type="hidden" name="collid" value="<?php echo $collid; ?>" />
					<input type="submit" name="formsubmit" value="Create/Refresh Darwin Core Archive" <?php if($blockSubmitMsg) echo 'disabled'; ?> />
					<?php
					if($blockSubmitMsg){
						echo '<span style="color:red;margin-left:10px;">'.$blockSubmitMsg.'</span>';
					}
					?>
				</div>
				<?php
				if($collArr['managementtype'] != 'Live Data' || $collArr['guidtarget'] != 'symbiotaUUID'){
					?>
					<div style="margin:10px;font-weight:bold">
						NOTE: all records lacking occurrenceID GUIDs will be excluded
					</div>
					<?php
				}
				?>
			</form>
		</fieldset>
		<?php
	}
	else{
		$catID = (isset($DEFAULTCATID)?$DEFAULTCATID:0);
		$catTitle = $dwcaManager->getCategoryName($catID);
		if($IS_ADMIN){
			if($action == 'Create/Refresh Darwin Core Archive(s)'){
				echo '<ul>';
				$dwcaManager->setVerboseMode(3);
				$dwcaManager->setLimitToGuids(true);
				$dwcaManager->batchCreateDwca($_POST['coll']);
				echo '</ul>';
				$collManager->batchTriggerGBIFCrawl($_POST['coll']);
			}
			?>
			<div id="dwcaadmindiv" style="margin:10px;display:<?php echo ($emode?'block':'none'); ?>;" >
				<form name="dwcaadminform" action="datapublisher.php" method="post" onsubmit="return verifyDwcaAdminForm(this)">
					<fieldset style="padding:15px;">
						<legend><b>Publish / Refresh <?php echo $catTitle; ?> DwC-A Files</b></legend>
						<div style="margin:10px;">
							<input name="collcheckall" type="checkbox" value="" onclick="checkAllColl(this)" /> Select/Deselect All<br/><br/>
							<?php
							$collList = $dwcaManager->getCollectionList($catID);
							foreach($collList as $k => $v){
								$errMsg = '';
								if(!$v['guid']){
									$errMsg = 'Missing GUID source';
								}
								elseif($v['url'] && !strpos($v['url'],str_replace('www.', '', $_SERVER["SERVER_NAME"]))){
									$baseUrl = substr($v['url'],0,strpos($v['url'],'/content')).'/collections/datasets/datapublisher.php';
									$errMsg = 'Already published on different domain (<a href="'.$baseUrl.'" target="_blank">'.substr($baseUrl,0,strpos($baseUrl,'/',10)).'</a>)';
								}
								echo '<input name="coll[]" type="checkbox" value="'.$k.'" '.($errMsg?'DISABLED':'').' />';
								echo '<a href="../misc/collprofiles.php?collid='.$k.'" target="_blank">'.$v['name'].'</a>';
								if($errMsg) echo '<span style="color:red;margin-left:15px;">'.$errMsg.'</span>';
								echo '<br/>';
							}
							?>
						</div>
						<fieldset style="margin:10px;padding:15px;">
							<legend><b>Options</b></legend>
							<input type="checkbox" name="dets" value="1" <?php echo ($includeDets?'CHECKED':''); ?> /> Include Determination History<br/>
							<input type="checkbox" name="imgs" value="1" <?php echo ($includeImgs?'CHECKED':''); ?> /> Include Image URLs<br/>
							<input type="checkbox" name="redact" value="1" <?php echo ($redactLocalities?'CHECKED':''); ?> /> Redact Sensitive Localities<br/>
						</fieldset>
						<div style="clear:both;margin:20px;">
							<input type="hidden" name="collid" value="<?php echo $collid; ?>" />
							<input type="submit" name="formsubmit" value="Create/Refresh Darwin Core Archive(s)" />
						</div>
					</fieldset>
				</form>
			</div>
			<?php
		}
		if($dwcaArr = $dwcaManager->getDwcaItems()){
			if($catTitle) echo '<div style="font-weight:bold;font-size:140%;margin:50px 0px 15px 0px;">'.$catTitle.' DwC-Archive Files</div>';
			?>
			<table class="styledtable" style="font-family:Arial;font-size:12px;margin:10px;">
				<tr><th>Code</th><th>Collection Name</th><th>DwC-Archive</th><th>Metadata</th><th>Pub Date</th></tr>
				<?php
				foreach($dwcaArr as $k => $v){
					?>
					<tr>
						<td><?php echo '<a href="../misc/collprofiles.php?collid='.$v['collid'].'">'.str_replace(' DwC-Archive','',$v['title']).'</a>'; ?></td>
						<td><?php echo substr($v['description'],24); ?></td>
						<td class="nowrap">
							<?php
							echo '<a href="'.$v['link'].'">DwC-A ('.$dwcaManager->humanFileSize($v['link']).')</a>';
							if($IS_ADMIN){
								?>
								<form action="datapublisher.php" method="post" style="display:inline;" onsubmit="return window.confirm('Are you sure you want to delete this archive?');">
									<input type="hidden" name="colliddel" value="<?php echo $v['collid']; ?>">
									<input type="image" src="../../images/del.png" name="action" value="DeleteCollid" title="Delete Archive" style="width:15px;" />
								</form>
								<?php
							}
							?>
						</td>
						<td>
							<?php
							echo '<a href="'.$urlPrefix.'collections/datasets/emlhandler.php?collid='.$v['collid'].'">EML</a>';
							?>
						</td>
						<td class="nowrap"><?php echo date('Y-m-d', strtotime($v['pubDate'])); ?></td>
					</tr>
					<?php
				}
				?>
			</table>
			<?php
		}
		else{
			echo '<div style="margin:10px;font-weight:bold;">There are no publishable collections</div>';
		}
		if($catID){
			if($addDwca = $dwcaManager->getAdditionalDWCA($catID)){
				echo '<div style="font-weight:bold;font-size:140%;margin:50px 0px 15px 0px;">Additional Data Sources within the Portal Network</div>';
				echo '<ul>';
				foreach($addDwca as $domanName => $domainArr){
					echo '<li><a href="'.$domainArr['url'].'/collections/datasets/datapublisher.php'.'" target="_blank">http://'.$domanName.'</a> - '.$domainArr['cnt'].' Archives</li>';
				}
				echo '</ul>';
			}
		}
	}
	?>
</div>
<?php
include($SERVER_ROOT.'/footer.php');
?>
</body>
</html>