<?php

/**
 * @version 0.0.0.1
 */

class App_Model_Department extends App_BaseTable{
	
	protected $_name = "wd_keshi";
	protected $_db = null;
	
	public function _setup(){
		$this->_db = $GLOBALS['dbwd'];
		
		parent::_setup();
	}
	
	public function init(){
		parent::init();
	}
	
	/**
	 * 得到所有的一级科室及其所对应的二级科室
	 * @author gaoqing
	 * 2015年9月9日
	 * @param void 空
	 * @return array 所有的一级科室及其所对应的二级科室
	 */
	public function getAllDepartment() {
		$allDepartmentArr = array();
		
		$sql = $this->getSQL(true, " id, name ", " AND pID = 0 ");
		$statement = $this->_db->prepare($sql);
		$statement->execute();
		while ($temp = $statement->fetch(PDO::FETCH_ASSOC)){
			$tempArr = array();
			
			//得到当前一级科室的 id,
			$oneLevelDepartmentID = $temp['id'];
			
			//得到当前一级科室下的二级科室
			$latestLevelArr = $this->getLatestLevelChild($oneLevelDepartmentID);
			
			$tempArr['father'] = $temp;
			$tempArr['child'] = $latestLevelArr;
			
			$allDepartmentArr[] = $tempArr;
		}
		return $allDepartmentArr;
	}
	
	/**
	 * 得到一级科室下指定数目的疾病集
	 * @author gaoqing
	 * 2015年9月9日
	 * @param int $classid 科室 id 
	 * @return array 一级科室下指定数目的疾病集
	 */
	public function getOneLevelDisease($classid) {
		$childArr = array();
		
		/*
		 * 1、根据当前一级科室，查询出其所有的二级科室 id 
		 * 2、根据 二级科室的 id ，查询出每个科室的疾病，各个科室的疾病各取一条，放到数组中
		 */
		
		//1、根据当前一级科室，查询出其所有的二级科室 id 
		$sql = $this->getSQL(true, " id ", " AND pID = ? ");
		$statement = $this->_db->prepare($sql);
		$statement->execute(array($classid));
		
		while ($temp = $statement->fetch(PDO::FETCH_ASSOC)) {
			$twoLevelClassid = empty($temp['id']) ? 0 : $temp['id'];
			
			//2、根据 二级科室的 id ，查询出每个科室的疾病，各个科室的疾病各取一条，放到数组中
			$diseaseInfo = $this->getDiseaseOfDepartment($twoLevelClassid, 0, 1);
			
			$childArr[] = $diseaseInfo;
		}
		
		//处理为空的情况
		if (empty($childArr)) {
			$childArr['id'] = 0;
			$childArr['name'] = "";
		}
		return $childArr;
	}
	
	/**
	 * 得到科室表中，疾病的数据
	 * @author gaoqing
	 * 2015年9月9日
	 * @param int $classid 科室的 id 
	 * @param int $start 开始位置
	 * @param int $len 获取的个数
	 * @return array 科室表中，疾病的数据
	 */
	public function getDiseaseOfDepartment($classid, $start, $len) {
		$diseaseInfo = array();
		$temp = array();
		
		$sql = $this->getSQL(true, " id, name ", " AND pID = ? ", $start, $len);
		$statement = $this->_db->prepare($sql);
		$statement->execute(array($classid));
		$temp = $statement->fetch(PDO::FETCH_ASSOC);
		
		if (!empty($temp)) {
			$diseaseInfo = $temp;
		}else {
			$diseaseInfo['id'] = 0;
			$diseaseInfo['name'] = "";
		}
		return $diseaseInfo;
	}
	
	/**
	 * 得到指定科室下的所有一级子科室集
	 * @author gaoqing
	 * 2015年9月8日
	 * @param int $classid 科室 id 
	 * @return array 指定科室下的所有一级子科室集
	 */
	public function getLatestLevelChild($classid) {
		$childArr = array();
		
		$sql = $this->getSQL(false, " id, name ", " AND pID = ? ");
		$statement = $this->_db->prepare($sql);
		$statement->execute(array($classid));
		$childArr = $statement->fetchAll(PDO::FETCH_ASSOC);
		
		return $childArr;
	}
	
	/**
	 * 根据科室的 id，得到科室的 名称
	 * @author gaoqing
	 * 2015年9月11日
	 * @param string $classid 科室的 id 
	 * @return string 科室的名称
	 */
	public function getClassNameByClassid($classid) {
		$classname = "";
		
		//得到当前科室 id 下的科室信息
		$currentDepartment = $this->getDepartmentSimpleInfo($classid);
	
		if (!empty($currentDepartment)) {
			
			if ($currentDepartment['child'] != 0) {
				$classname = $currentDepartment['name'];
			}else {
				
				//父科室
				$pid = $currentDepartment['pID'];
				$parentDepartment = $this->getDepartmentSimpleInfo($pid);
				$classname = empty($parentDepartment) ? "" : $parentDepartment['name'];
			}
		}
		return $classname;
	}
	/**
	 * 根据科室的 id ，得到当前科室下的 pID, 科室名称，和 子科室
	 * @author gaoqing
	 * 2015年9月11日
	 * @param int $classid 科室的 id
	 * @return array 当前科室信息
	 */private function getDepartmentSimpleInfo($classid) {
	 	$department = array();
	 	
		$sql = $this->getSQL(true, "pID, name, child ", " AND id = ? ");
		$statement = $this->_db->prepare($sql);
		$statement->execute(array($classid));
		$temp = $statement->fetch(PDO::FETCH_ASSOC);
		
		if (!empty($temp)) {
			$department = $temp;
		}
		return $department;
	}

	
	/**
	 * 根据科室的名称，得到科室的 id
	 * @author gaoqing
	 * 2015年9月8日
	 * @param string $departmentName 科室的名称
	 * @return int 科室的id  
	 */
	public function getClassidByName($departmentName) {
		$classid = 0;
		
		$sql = $this->getSQL(true, " id ", " AND name = ? ");
		$statement = $this->_db->prepare($sql);
		$statement->execute(array($departmentName));
		$temp = $statement->fetch(PDO::FETCH_ASSOC);
		
		if (!empty($temp)) {
			$classid = $temp['id'];
		}
		return $classid;
	}
	
	/**
	 * 得到查询的 SQL 语句
	 * @author gaoqing
	 * 2015年9月08日
	 * @param boolean $isSimple 是否使用最简单的条件
	 * 						      （当为 true 时，如果 $start、$limitLen、$order 都为 null 时，则不使用默认的值，直接不添加相应的条件）
	 * @param int $selectField 要查询的字段
	 * @param int $where 查询条件
	 * @param int $start 查询条数限制的 开始 位置值
	 * @param int $limitLen limit 中的限制长度值
	 * @param int $order 排序规则
	 * @param string $dbName 表名称
	 * @return string 查询医生的 SQL 语句
	 */
	private function getSQL(
			$isSimple = true,
			$selectField = null,
			$where = null,
			$start = null,
			$limitLen = null,
			$order = null,
			$dbName = "wd_keshi")
	{
		$sql = "";
	
		//查询字段
		$selectFieldStr = empty($selectField) ? " * " : $selectField ;
	
		//查询条件
		$baseWhere = ($dbName == "wd_keshi" ? " WHERE 1 = 1  " : " WHERE 1 = 1 ");
		$whereStr = empty($where) ? $baseWhere : $baseWhere . $where ;
	
		$defaultOrderStr = " ";
		if (!$isSimple) {
			$defaultOrderStr = " ORDER BY id ";
		}
		//排序条件
		$orderStr = empty($order) ? $defaultOrderStr : $order ;
	
		//查询条数限制
		$limitStr = "";
		if (!empty($limitLen)) {
			$startNum = empty($start) ? 0 : $start;
			$limitStr = " LIMIT " . $startNum ." , " . $limitLen;
		}
		$sql = " SELECT ". $selectFieldStr ." FROM " . $dbName . $whereStr . $orderStr . $limitStr;
		return $sql;
	}	
	
	
}



?>