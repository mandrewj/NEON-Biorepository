<?php
include_once('../../../config/symbini.php'); 
include_once($serverRoot.'/classes/OccurrenceCrowdSource.php');
header("Content-Type: text/html; charset=".$charset);
if(!$symbUid) header('Location: ../../../profile/index.php?refurl=../collections/editor/crowdsource/controlpanel.php?'.$_SERVER['QUERY_STRING']);

$collid = array_key_exists('collid',$_REQUEST)?$_REQUEST['collid']:0;
$uid = array_key_exists('uid',$_REQUEST)?$_REQUEST['uid']:0;
$rStatus = array_key_exists('rstatus',$_REQUEST)?$_REQUEST['rstatus']:'5,10';
$start = array_key_exists('start',$_REQUEST)?$_REQUEST['start']:0;
$limit = array_key_exists('limit',$_REQUEST)?$_REQUEST['limit']:100;
$action = array_key_exists('action',$_REQUEST)?$_REQUEST['action']:'';

$csManager = new OccurrenceCrowdSource();
//If collid is null, it will be assumed that current user wants to review their own specimens (they can still edit pending, closed specimen can't be editted)
$csManager->setCollid($collid);

$isEditor = 0; 
if(array_key_exists('CollAdmin',$USER_RIGHTS) && in_array($collid,$USER_RIGHTS['CollAdmin'])){
	$isEditor = 1;
}

$statusStr = '';
if($action == 'Submit Reveiws'){
	$occidArr = $_POST['occid'];
	$pointArr = $_POST['points'];
	$commentArr = $_POST['comment'];
	if($occidArr) $statusStr = $csManager->submitReviews($occidArr,$pointArr,$commentArr);
}

$projArr = $csManager->getProjectDetails();
?>
<!DOCTYPE html>
<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=<?php echo $charset; ?>">
	<title><?php echo $defaultTitle; ?> Crowdsourcing Reviewer</title>
    <link type="text/css" href="../../../css/main.css" rel="stylesheet" />
	<link type="text/css" href="../../../css/base.css" rel="stylesheet" />
	<script type="text/javascript">
		function selectAll(cbObj){
			var cbStatus = cbObj.checked;
			var f = cbObj.form;
			for(var i = 0; i < f.length; i++) {
				if(f.elements[i].name == "occid[]") f.elements[i].checked = cbStatus;
			}
		}

		function expandNotes(textObj){
			textObj.style.width = "300px";
		}

		function collapseNotes(textObj){
			textObj.style.width = "60px";
		}

		function validateReviewForm(f){
			for(var i = 0; i < f.length; i++) {
				if(f.elements[i].name == "occid[]" && f.elements[i].checked) return true;
			}
			alert("No records have been selected");
			return false;
		}
	</script>
</head>
<body>
	<?php 	
	$displayLeftMenu = false;
	include($serverRoot.'/header.php');
	?>
	<div class='navpath'>
		<a href="../../../index.php">Home</a> &gt;&gt;
		<a href="central.php">Source Board</a> &gt;&gt;
		<?php 
		if($collid) echo '<a href="controlpanel.php?collid='.$collid.'">Control Panel</a> &gt;&gt;';
		?>
		<b>Crowdsourcing Review</b>
	</div>
	<!-- inner text -->
	<div id="innertext">
		<?php
		if($statusStr){
			?>
			<hr/>
			<div style="margin:20px;color:<?php echo (substr($statusStr,0,5)=='ERROR'?'red':'green');?>">
				<?php echo $statusStr; ?>
			</div>
			<hr/>
			<?php 
		} 
		?>
		<div style="font-weight:bold;font-size:130%;float:left;width:465px;"><?php echo ($collid?$projArr['name']:$USER_DISPLAY_NAME); ?></div>
		<div style="float:left;">
			<form name="filter" action="review.php" method="post">
				<fieldset style="width:300px;text-align:left;">
					<legend><b>Filter</b></legend>
					<div style="margin:3px;">
						Review Status: 
						<select name="rstatus">
							<option value="5,10">All Records</option>
							<option value="5,10">----------------------</option>
							<option value="5" <?php echo ($rStatus==5?'SELECTED':''); ?>>Pending Review</option>
							<option value="10" <?php echo ($rStatus==10?'SELECTED':''); ?>>Closed (Approved)</option>
						</select>
					</div>
					<?php 
					if($collid){
						?>
						<div style="margin:3px;">
							Editor: 
							<select name="uid">
								<option value="">All Editors</option>
								<option value="">----------------------</option>
								<?php 
								$editorArr = $csManager->getEditorList();
								foreach($editorArr as $eUid => $eName){
									echo '<option value="'.$eUid.'" '.($eUid==$uid?'SELECTED':'').'>'.$eName.'</option>'."\n";
								}
								?>
							</select>
						</div>
						<?php 
					}
					else{
						echo '<input name="uid" type="hidden" value="'.$uid.'" />';
					}
					?>
					<div style="margin:3px;">
						<input name="collid" type="hidden" value="<?php echo $collid; ?>" />
						<input name="action" type="submit" value="Filter Records" />
					</div>
				</fieldset>
			</form>
		</div>
		<div style="clear:both;">
			<?php 
			if($recArr = $csManager->getReviewArr($start,$limit,$uid,$rStatus)){
				$totalCnt = $recArr['totalcnt'];
				unset($recArr['totalcnt']);
				//Set up navigation string
				$pageCnt = count($recArr);
				$lastStart = ((number_format($totalCnt/100)) * $limit);
				//echo json_encode($recArr);
				$end = ($start + $pageCnt);
				$urlPrefix = 'review.php?collid='.$collid.'&uid='.$uid.'&rstatus='.$rStatus;
				$navStr = '<b>';
				if($start > 0) $navStr .= '<a href="'.$urlPrefix.'&start=0&limit=100">';
				$navStr .= '|&lt; ';
				if($start > 0) $navStr .= '</a>';
				$navStr .= '&nbsp;&nbsp;&nbsp;';
				if($start > 0) $navStr .= '<a href="'.$urlPrefix.'&start='.($start-$limit).'&limit=100">';
				$navStr .= '&lt;&lt;';
				if($start > 0) $navStr .= '</a>';
				$navStr .= '&nbsp;&nbsp;|&nbsp;&nbsp;'.($start + 1).' - '.($end).'&nbsp;&nbsp;|&nbsp;&nbsp;';
				if($totalCnt > $limit) $navStr .= '<a href="'.$urlPrefix.'&start='.($start+$limit).'&limit=100">';
				$navStr .= '&gt;&gt;';
				if($totalCnt > $limit) $navStr .= '</a>';
				$navStr .= '&nbsp;&nbsp;&nbsp;';
				if(($start+$pageCnt) < $totalCnt) $navStr .= '<a href="'.$urlPrefix.'&start='.$lastStart.'&limit=100">';
				$navStr .= '&gt;|';
				if(($start+$pageCnt) < $totalCnt) $navStr .= '</a> ';
				$navStr .= '</b>';
				?>
				<div>
					<div style="float:left;"><b>Total Record Count:</b> <?php echo $totalCnt; ?></div>
					<?php
					if($totalCnt > 0){
						?>
						<div style="float:left;margin-left:500px;"><?php echo $navStr; ?></div>
						<div style="clear:both;">
							<form name="reviewform" method="post" action="review.php" onsubmit="return validateReviewForm(this)">
								<table class="styledtable">
									<tr>
										<?php 
										if($collid) echo '<th><span title="Select All"><input name="selectall" type="checkbox" onclick="selectAll(this)" /></span></th>';
										?>
										<th>Points</th>
										<th>Comments</th>
										<th>Edit</th>
										<?php 
										//Display table header
										$header = $csManager->getHeaderArr();
										foreach($header as $v){
											echo '<th>'.$v.'</th>';
										}
										?>
									</tr>
									<?php 
									$cnt = 0;
									//echo json_encode($recArr);
									foreach($recArr as $occid => $rArr){
									?>
										<tr <?php echo ($cnt%2?'class="alt"':'') ?>>
											<?php 
											$notes = '';
											if(isset($rArr['notes'])) $notes = $rArr['notes'];
											$points = 2;
											if(isset($rArr['points'])) $points = $rArr['points'];
											if($collid){
												echo '<td><input name="occid[]" type="checkbox" value="'.$occid.'" /></td>';
												//echo '<td><input name="points[]" type="text" value="'.$points.'" style="width:15px;" /></td>';
												echo '<td><select name="points[]" style="width:45px;">';
												echo '<option value="0" '.($points=='0'?'SELECTED':'').'>0</option>';
												echo '<option value="1" '.($points=='1'?'SELECTED':'').'>1</option>';
												echo '<option value="2" '.($points=='2'?'SELECTED':'').'>2</option>';
												echo '<option value="3" '.($points=='3'?'SELECTED':'').'>3</option>';
												echo '</select></td>';
												echo '<td><input name="comment[]" type="text" value="'.$notes.'" style="width:60px;" onfocus="expandNotes(this)" onblur="collapseNotes(this)"/></td>';
											}
											else{
												echo '<td><input name="points[]" type="text" value="'.$points.'" style="width:15px;" DISABLED /></td>';
												echo '<td>'.$notes.'</td>';
											}
											?>
											<td>
												<?php 
												if($isEditor || $rArr['reviewstatus'] == 5){ 
													echo '<a href="../occurrenceeditor.php?csmode=1&occid='.$occid.'" target="_blank">';
													echo '<img src="../../../images/edit.png" style="border:solid 1px gray;height:13px;" />'; 
													echo '</a>';
												}
												else{
													echo '<img src="../../../images/cross-out.png" style="border:solid 1px gray;height:13px;" />';
												}
												?>
											</td>
											<?php 
											foreach($header as $v){
												$displayStr = $rArr[$v];
												if(strlen($displayStr) > 40){
													$displayStr = substr($displayStr,0,40).'...';
												}
												echo '<td>'.$displayStr.'</td>'."\n";
											}
											?>
										</tr>
										<?php
										$cnt++; 
									}
									?>
								</table>
								<?php
								if($collid){
									echo '<input name="collid" type="hidden" value="'.$collid.'" />';
									echo '<input name="rstatus" type="hidden" value="'.$rStatus.'" />';
									echo '<input name="uid" type="hidden" value="'.$uid.'" />';
									echo '<input name="action" type="submit" value="Submit Reveiws" />';
								}
								?>
							</form>
						</div>
						<div style="margin-left:500px;">
							<?php echo $navStr; ?>
						</div>
						<?php
					}
					else{
						echo '<div style="clear:both;font-weight:bold;font-size:120%;padding-top:30px;">There are no records for '.$eName.' with given processing status</div>';
					}
					?>
				</div>
				<?php 
			}
			?>
		</div>
	</div>
	<?php
	include($serverRoot.'/footer.php');
	?>
</body>
</html>