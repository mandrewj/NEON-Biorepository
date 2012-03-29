<?php
include_once($serverRoot.'/config/dbconnection.php');
 
class OccurrenceGeorefTools {

	private $conn;
	private $collId;
	private $collName;
	private $qryVars = array();

	function __construct($type = 'readonly') {
		$this->conn = MySQLiConnectionFactory::getCon($type);
	}

	function __destruct(){
 		if(!($this->conn === false)) $this->conn->close();
	}

	public function getLocalityArr(){
		$retArr = array();
		$sql = 'SELECT occid, country, stateprovince, county, locality, verbatimcoordinates '. 
			'FROM omoccurrences '. 
			'WHERE (collid = '.$this->collId.') AND (locality IS NOT NULL) ';
		if($this->qryVars){
			if(array_key_exists('qvstatus',$this->qryVars)){
				$sql .= 'AND ((decimalLatitude IS NULL) OR (georeferenceVerificationStatus LIKE "'.$this->qryVars['qvstatus'].'%")) ';
			}
			else{
				$sql .= 'AND (decimalLatitude IS NULL) AND (georeferenceVerificationStatus IS NULL) ';
			}
			foreach($this->qryVars as $k => $v){
				if($v && $k != 'qvstatus'){
					if($k == 'qlocality'){
						$sql .= 'AND (locality LIKE "%'.$v.'%") ';
					}
					elseif($k == 'qcounty'){
						$sql .= 'AND (county LIKE "'.$v.'%") ';
					}
					else{
						$sql .= 'AND ('.substr($k,1).' = "'.$v.'") ';
					}
				}
			}
		}
		$sql .= 'ORDER BY locality,county,verbatimcoordinates';
		//echo $sql;
		$totalCnt = 0;
		$locCnt = 1;
		$locStr = '';$extraStr = '';
		$rs = $this->conn->query($sql);
		while($r = $rs->fetch_object()){
			if($locStr != trim($r->locality) || $extraStr != trim($r->extra)){
				$locStr = trim($r->locality);
				$extraStr = trim($r->extra);
				$totalCnt++;
				$retArr[$totalCnt]['occid'] = $r->occid;
				$retArr[$totalCnt]['locality'] = $locStr;
				$retArr[$totalCnt]['country'] = $country;
				$retArr[$totalCnt]['stateprovince'] = $stateprovince;
				$retArr[$totalCnt]['county'] = $county;
				$retArr[$totalCnt]['verbatimcoordinates'] = $verbatimcoordinates;
				$retArr[$totalCnt]['cnt'] = 1;
				$locCnt = 1;
			}
			else{
				$locCnt++;
				$newOccidStr = $retArr[$totalCnt]['occid'].','.$r->occid;
				$retArr[$totalCnt]['occid'] = $newOccidStr;
				$retArr[$totalCnt]['cnt'] = $locCnt;
			}
		}
		$rs->close();
		usort($retArr,array('OccurrenceGeorefTools', '_cmpLocCnt'));
		return $retArr;
	}

	public function updateCoordinates($geoRefArr){
		if($geoRefArr['decimallatitude'] && $geoRefArr['decimallongitude']){
			$sql = 'UPDATE omoccurrences '.
				'SET decimallatitude = '.$geoRefArr['decimallatitude'].', decimallongitude = '.$geoRefArr['decimallongitude'].
				',georeferenceverificationstatus = "'.$geoRefArr['georeferenceverificationstatus'].'"'.
				',georeferencesources = "'.$geoRefArr['georeferencesources'].'"'.
				',georeferenceremarks = CONCAT_WS("; ",georeferenceremarks,"'.$geoRefArr['georeferenceremarks'].'"'.
				',georeferencedBy = "'.$geoRefArr['georefby'].'"';
			if($geoRefArr['coordinateuncertaintyinmeters']){
				$sql .= ',coordinateuncertaintyinmeters = '.$geoRefArr['coordinateuncertaintyinmeters'];
			}
			if($geoRefArr['geodeticdatum']){
				$sql .= ', geodeticdatum = '.$geoRefArr['geodeticdatum'];
			}
			if($geoRefArr['minimumelevationinmeters']){
				$sql .= ',minimumelevationinmeters = IF(minimumelevationinmeters IS NULL,'.$geoRefArr['minimumelevationinmeters'].',minimumelevationinmeters)';
			}
			if($geoRefArr['maximumelevationinmeters']){
				$sql .= ',maximumelevationinmeters = IF(maximumelevationinmeters IS NULL,'.$geoRefArr['maximumelevationinmeters'].',maximumelevationinmeters)';
			}
			
			$localList = $geoRefArr['locallist'];
			if(is_array($localList)){
				$sql .= ' WHERE occid IN('.implode(','.$localList).')';
			}
			else{
				$sql .= ' WHERE occid = '.$localList;
				
			}
			echo $sql;
			//$this->conn->query($sql);
		}
	}

	public function getCoordStatistics(){
		$retArr = array();
		$totalCnt = 0;
		$sql = 'SELECT COUNT(occid) AS cnt '. 
			'FROM omoccurrences '. 
			'WHERE (collid = '.$this->collId.')'; 
		$rs = $this->conn->query($sql);
		while($r = $rs->fetch_object()){
			$totalCnt = $r->cnt;
		}
		$rs->close();
		
		$sql = 'SELECT COUNT(occid) AS cnt '. 
			'FROM omoccurrences '. 
			'WHERE (collid = '.$this->collId.') AND (decimalLatitude IS NULL) AND (georeferenceVerificationStatus IS NULL) ';
		$k = '';
		$limitedSql = '';
		if($this->qryVars){
			if(array_key_exists('qcounty',$this->qryVars)){
				$limitedSql = 'AND county = "'.$this->qryVars['qcounty'].'" ';
				$k = $this->qryVars['qcounty'];
			}
			elseif(array_key_exists('qstate',$this->qryVars)){
				$limitedSql = 'AND stateprovince = "'.$this->qryVars['qstate'].'" ';
				$k = $this->qryVars['qstate'];
			}
			elseif(array_key_exists('qcountry',$this->qryVars)){
				$limitedSql = 'AND country = "'.$this->qryVars['qcountry'].'" ';
				$k = $this->qryVars['qcountry'];
			}
		}
		//Count limited to country, state, or county
		if($k){
			if($rs = $this->conn->query($sql.$limitedSql)){
				if($r = $rs->fetch_object()){
					$retArr[$k] = $r->cnt;
				}
				$rs->close();
			}
		}
		//Full count
		if($rs = $this->conn->query($sql)){
			if($r = $rs->fetch_object()){
				$retArr['Total Number'] = $r->cnt;
				$retArr['Total Percentage'] = round($r->cnt*100/$totalCnt,1);
			}
			$rs->close();
		}
		
		return $retArr;
	} 

	public function setCollId($cid){
		$this->collId = $cid;
		$sql = 'SELECT collectionname FROM omcollections WHERE collid = '.$cid;
		$rs = $this->conn->query($sql);
		while($r = $rs->fetch_object()){
			$this->collName = $r->collectionname;
		}
		$rs->close();
	}
	
	public function setQueryVariables($k,$v){
		$this->qryVars[$k] = $v;
	}

	public function getCollName(){
		return $this->collName;
	}

	public function getCountryArr(){
		$retArr = array();
		$sql = 'SELECT DISTINCT country '.
			'FROM omoccurrences WHERE collid = '.$this->collId.' ORDER BY country';
		$rs = $this->conn->query($sql);
		while($r = $rs->fetch_object()){
			$cStr = trim($r->country);
			if($cStr) $retArr[] = $cStr;
		}
		$rs->close();
		return $retArr;
	}
	
	public function getStateArr($countryStr = ''){
		$retArr = array();
		$sql = 'SELECT DISTINCT stateprovince '.
			'FROM omoccurrences WHERE collid = '.$this->collId.' ';
		if($countryStr){
			$sql .= 'AND country = "'.$countryStr.'" ';
		}
		$sql .= 'ORDER BY stateprovince';
		$rs = $this->conn->query($sql);
		while($r = $rs->fetch_object()){
			$sStr = trim($r->stateprovince);
			if($sStr) $retArr[] = $sStr;
		}
		$rs->close();
		return $retArr;
	}
	
	public function getCountyArr($countryStr = '',$stateStr = ''){
		$retArr = array();
		$sql = 'SELECT DISTINCT county '.
			'FROM omoccurrences WHERE collid = '.$this->collId.' ';
		if($countryStr){
			$sql .= 'AND country = "'.$countryStr.'" ';
		}
		if($stateStr){
			$sql .= 'AND stateprovince = "'.$stateStr.'" ';
		}
		$sql .= 'ORDER BY county';
		$rs = $this->conn->query($sql);
		while($r = $rs->fetch_object()){
			$cStr = trim($r->county);
			if($cStr) $retArr[] = $cStr;
		}
		$rs->close();
		return $retArr;
	}

	private function cleanStr($str){
 		$newStr = trim($str);
 		$newStr = preg_replace('/\s\s+/', ' ',$newStr);
 		$newStr = str_replace('"',"'",$newStr);
 		$newStr = $this->clCon->real_escape_string($newStr);
 		return $newStr;
 	}

 	private static function _cmpLocCnt ($a, $b){
		$aCnt = $a['cnt'];
		$bCnt = $b['cnt'];
		if($aCnt == $bCnt){
			return 0;
		}
		return ($aCnt > $bCnt) ? -1 : 1;
	}
}
?> 