<?php
include_once('OccurrenceManager.php');

class OccurrenceChecklistManager extends OccurrenceManager{

	private $checklistTaxaCnt = 0;

	public function __construct(){
		parent::__construct();
	}

	public function __destruct(){
		parent::__destruct();
	}

	public function getChecklistTaxaCnt(){
		return $this->checklistTaxaCnt;
	}

	public function getChecklist($taxonAuthorityId){
		$returnVec = Array();
		$this->checklistTaxaCnt = 0;
		$sqlWhere = $this->getSqlWhere();
		if($sqlWhere){
			$sql = "";
			if($taxonAuthorityId && is_numeric($taxonAuthorityId)){
				$sql = 'SELECT DISTINCT ts2.family, t.sciname '.
					'FROM omoccurrences o INNER JOIN taxstatus ts1 ON o.TidInterpreted = ts1.Tid '.
					'INNER JOIN taxa t ON ts1.TidAccepted = t.Tid '.
					'INNER JOIN taxstatus ts2 ON t.tid = ts2.tid ';
				$sql .= $this->getTableJoins($sqlWhere);
				$sql .= str_ireplace("o.sciname","t.sciname",str_ireplace("o.family","ts.family",$sqlWhere));
				$sql .= ' AND ts1.taxauthid = '.$taxonAuthorityId.' AND ts2.taxauthid = '.$taxonAuthorityId.' AND t.RankId > 140 ';
			}
			else{
				$sql = 'SELECT DISTINCT IFNULL(ts1.family,o.family) AS family, o.sciname '.
					'FROM omoccurrences o LEFT JOIN taxa t ON o.tidinterpreted = t.tid '.
					'LEFT JOIN taxstatus ts1 ON t.tid = ts1.tid ';
				$sql .= $this->getTableJoins($sqlWhere);
				$sql .= $sqlWhere." AND (t.rankid > 140) AND (ts1.taxauthid = 1) ";
			}
			//echo "<div>".$sql."</div>";
			$result = $this->conn->query($sql);
			while($row = $result->fetch_object()){
				$family = strtoupper($row->family);
				if(!$family) $family = 'undefined';
				$sciName = $row->sciname;
				if($sciName && substr($sciName,-5)!='aceae'){
					$returnVec[$family][] = $sciName;
					$this->checklistTaxaCnt++;
				}
			}
			$result->free();
		}
		return $returnVec;
	}

	public function getTidChecklist($tidArr,$taxonFilter){
		$returnVec = Array();
		$tidStr = implode(',',$tidArr);
		$this->checklistTaxaCnt = 0;
		$sql = "";
		$sql = 'SELECT DISTINCT ts.family, t.sciname '.
			'FROM (taxstatus AS ts1 INNER JOIN taxa AS t ON ts1.TidAccepted = t.Tid) '.
			'INNER JOIN taxstatus AS ts ON t.tid = ts.tid '.
			'WHERE ts1.tid IN('.$tidStr.') '.
			'AND ts1.taxauthid = '.$taxonFilter.' AND ts.taxauthid = '.$taxonFilter.' AND t.RankId > 140 ';
		//echo "<div>".$sql."</div>";
		$result = $this->conn->query($sql);
		while($row = $result->fetch_object()){
			$family = strtoupper($row->family);
			if(!$family) $family = 'undefined';
			$sciName = $row->sciname;
			if($sciName && substr($sciName,-5)!='aceae'){
				$returnVec[$family][] = $sciName;
				$this->checklistTaxaCnt++;
			}
		}
		return $returnVec;
	}

	public function buildSymbiotaChecklist($taxonAuthorityId,$tidArr = ''){
		global $CLIENT_ROOT,$SYMB_UID;
		$conn = $this->getConnection("write");
		$sqlCreateCl = "";
		$expirationTime = date('Y-m-d H:i:s',time()+259200);
		$searchStr = "";
		$tidStr = "";
		if($tidArr){
			$tidStr = implode(',',$tidArr);
		}
		if($this->getTaxaSearchStr()) $searchStr .= "; ".$this->getTaxaSearchStr();
		if($this->getLocalSearchStr()) $searchStr .= "; ".$this->getLocalSearchStr();
		if($this->getDatasetSearchStr()) $searchStr .= "; ".$this->getDatasetSearchStr();
		//$searchStr = substr($searchStr,2,250);
		//$nameStr = substr($searchStr,0,35)."-".time();
		$sqlTaxaBase = '';
		if($tidStr){
			if(is_numeric($taxonAuthorityId)){
				$sqlTaxaBase .= 'FROM taxstatus ts INNER JOIN taxa t ON ts.TidAccepted = t.Tid WHERE ts.tid IN('.$tidStr.') AND ts.taxauthid = '.$taxonAuthorityId;
			}
			else{
				$sqlTaxaBase .= 'FROM taxa t WHERE t.tid IN('.$tidStr.') ';
			}
		}
		else{
			$sqlWhere = $this->getSqlWhere();
			if($sqlWhere){
				if(is_numeric($taxonAuthorityId)){
					$sqlTaxaBase .= 'FROM omoccurrences o INNER JOIN taxstatus ts1 ON o.TidInterpreted = ts1.Tid '.
						'INNER JOIN taxa t ON ts1.TidAccepted = t.Tid '.$this->getTableJoins($sqlWhere).
						str_ireplace('o.sciname','t.sciname',str_ireplace('o.family','ts1.family',$sqlWhere)).'AND ts1.taxauthid = '.$taxonAuthorityId;
				}
				else{
					$sqlTaxaBase .= 'FROM omoccurrences o INNER JOIN taxa t ON o.TidInterpreted = t.tid '.$this->getTableJoins($sqlWhere).$sqlWhere;
				}
			}
		}
		//echo "sqlTaxaInsert: ".$sqlTaxa; exit;

		$dynClid = 0;
		if($sqlTaxaBase){
			$sqlCreateCl = 'INSERT INTO fmdynamicchecklists ( name, details, uid, type, notes, expiration ) '.
				"VALUES ('Specimen Checklist #".time()."', 'Generated ".date('Y-m-d H:i:s',time())."', '".$SYMB_UID."', 'Specimen Checklist', '', '".$expirationTime."') ";
			if($conn->query($sqlCreateCl)){
				$dynClid = $conn->insert_id;
				$sqlTaxa = 'INSERT IGNORE INTO fmdyncltaxalink ( tid, dynclid ) SELECT DISTINCT t.tid, '.$dynClid.' '.$sqlTaxaBase.' AND t.RankId > 180';
				$conn->query($sqlTaxa);

				//Delete checklists that are greater than one week old
				$conn->query('DELETE FROM fmdynamicchecklists WHERE expiration < now()');
			}
			else{
				echo "ERROR: ".$conn->error;
				//echo "insertSql: ".$sqlCreateCl;
			}
			if($conn !== false) $conn->close();
		}
		return $dynClid;
	}

	public function getTaxonAuthorityList(){
		$taxonAuthorityList = Array();
		$sql = "SELECT ta.taxauthid, ta.name FROM taxauthority ta WHERE (ta.isactive <> 0)";
		$result = $this->conn->query($sql);
		while($row = $result->fetch_object()){
			$taxonAuthorityList[$row->taxauthid] = $row->name;
		}
		return $taxonAuthorityList;
	}
}
?>