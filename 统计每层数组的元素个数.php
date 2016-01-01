<?php
		$arr = [
					'品牌女装' => [
									'连衣裙' =>	[
													'ONLY',
													'秋依水'
									],
									'半身裙' => [

									],
									'旗袍'	 => [
													'长旗袍',
													'短旗袍',
													'单旗袍',
													'夹旗袍'
									]

					],
					'精品男装' => [
									'皮衣' => [

									],
									'夹克' => [

									]									

					]

	   ];

		//$arr = array();
		//$arr = [1 => array(1)];
		/**
		*函数作用：计算多维数组每层数组元素个数。
		*@var Array $array 统计的数组
		*@var int $layer 记录在哪一层数组
		*@var Array $count 记录每层数组的共有多少个元素
		*@return Array 形如：[0=>2,1=>3] 含义：“最外层有两个，第一层有3个”
		*/


	   function CountEachArr($array, $layer = 0,$count = []){
	   		$count[$layer] = count($array);							//统计数组元素个数
	   		$tempArr = [];											//临时数组，存放$array的里层数组
	   		foreach ($array as $key => $value) {					//遍历数组，把内层是数组的元素转换成外层数组，相当于把二维变成一维，三维变成二维
	   			if(is_array($value)){
	   				$tempArr = array_merge($tempArr, $value);	
	   			}
	   		}

	   		if (count($tempArr) > 0) {								//如果$tempArr里面没有元素。表示已经遍历完毕。
	   			$layer = $layer + 1;								//如果$tempArr里面有元素，表示没有遍历完毕，需要递归的统计元素
	   			return CountEachArr($tempArr, $layer, $count);
	   		}
	   		return $count;	

	   }

	   var_dump(CountEachArr($arr));
	   //print_r($data);










?>