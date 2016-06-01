<?php
namespace tptophp {

	use phptojs\util\OBFileWriter;

	require_once __DIR__ . "/OBFileWriter.php";

	$obfw=null;
	$actualFileName = null;

	function convert($tsFilePath,$exportFilePath) {
		global $obfw, $actualFileName;
		$actualFileName=basename($tsFilePath);
		$obfw = new OBFileWriter($exportFilePath);
		$obfw->start();

		$lines = file($tsFilePath);

		$indentNum = 0;

		echo "<?php" . PHP_EOL;
		for ($lineNum = 0; $lineNum < count($lines); $lineNum++) {
			$line = trim($lines[$lineNum]);
			if (substr($line, 0, 2) == "//") {
				echo $line . PHP_EOL;
				continue;
			}
			$line = trim($line);
			if (strlen($line) == 0) {
				echo PHP_EOL;
				continue;
			}
			if (substr($line, 0, 14) == "declare module") {
				$lineNum = \tstophp\utils\parseModule($lines, $lineNum);
				continue;
			}
			if (substr($line, 0, 13) == "declare class") {
				echo "namespace {" . PHP_EOL;
				\tstophp\utils\indent();
				$lineNum = \tstophp\utils\parseClass($lines, $lineNum);
				\tstophp\utils\oudent();
				echo "}" . PHP_EOL;
				continue;
			}
			if (substr($line, 0, 16) == "declare function" || substr($line, 0, 15) == "export function") {
				echo "namespace {" . PHP_EOL;
				\tstophp\utils\indent();
				$lineNum = \tstophp\utils\parseFunction($lines, $lineNum);
				if ($obfw->isEnd()){
					break;
				}
				\tstophp\utils\oudent();
				echo "}" . PHP_EOL;
				continue;
			}

			$obfw->end();
			echo "1:undefined line {$actualFileName}:" . ($lineNum + 1) . ":'{$line}'";
			break;
		}
		$obfw->end();
	}
}
namespace tstophp\utils{

	use Nette\PhpGenerator\ClassType;
	use phptojs\util\OBFileWriter;

	function indent() {
		global $indentNum;
		return str_repeat("\t", ++$indentNum);
	}

	function getIndent() {
		global $indentNum;
		return str_repeat("\t", $indentNum);
	}

	function oudent() {
		global $indentNum;
		return str_repeat("\t", --$indentNum);
	}
	function parseModule($lines, $lineNum, $currentNamespace = []) {
		/**
		 * @var OBFileWriter $obfw
		 */
		global $obfw,$indentNum,$actualFileName;
		$indent = getIndent();
		$line = $lines[$lineNum++];
		$line = str_replace(["declare", "module", "{", '"', "'"], "", $line);
		$namespaces = explode(".",$line);
		foreach($namespaces as $namespace){
			$currentNamespace[]=trim($namespace);
		}
		echo $indent . "namespace " . join("\\", $currentNamespace) . " {" . PHP_EOL;
		$indent = indent();
		for (; $lineNum < count($lines); $lineNum++) {
			$line = trim($lines[$lineNum]);
			if (substr($line,0,2)=="//"){
				echo $indent.$line.PHP_EOL;
				continue;
			}
			if ($line==""){
				echo PHP_EOL;
				continue;
			}
			if ($line=="/**" || substr($line,0,1)=="*" || $line=="**/"){
				echo $indent.$line.PHP_EOL;
				continue;
			}

			if (substr($line, 0, 15) == "export function") {
				$lineNum = \tstophp\utils\parseFunction($lines, $lineNum);
				if ($obfw->isEnd()){
					return $lineNum;
				}
				continue;
			}
			if (substr($line, 0, 5) == "class" || substr($line, 0, 9) == "interface") {
				$lineNum = parseClass($lines, $lineNum);
				continue;
			}
			if (trim($line) == "}") {
				break;
			}
			if (substr($line, 0, 6) == "module") {
				$oldIndentNum=$indentNum;
				$indentNum=0;
				$indent=getIndent();
				echo $indent."}".PHP_EOL;
				$lineNum = parseModule($lines,$lineNum,$currentNamespace);
				echo $indent . "namespace " . join("\\", $currentNamespace) . " {" . PHP_EOL;
				$indentNum=$oldIndentNum;
				$indent=getIndent();
				continue;
			}
			if (substr($line, 0, 3) == "var" || substr($line, 0, 10) == "export var") {
				$line=trim(str_replace(["var","export"],"",$line));
				$parts = explode(":",$line);
				if (count($parts)==2 && trim($parts[1])=="Function"){
					if ($parts[0]=="Default"){
						$parts[0]="Default_";
					}
					echo $indent."function {$parts[0]}(){}".PHP_EOL;
					continue;
				}
				if (count($parts)==2){
					echo "{$indent}/**".PHP_EOL;
					echo "{$indent} * @const ".trim(str_replace(".","\\",$parts[1])).PHP_EOL;
					echo "{$indent} */".PHP_EOL;
					echo "{$indent}const ".trim($parts[0])."=null;".PHP_EOL;
				}
				continue;
			}
			if (substr($line, 0, 4) == "enum" || substr($line, 0, 11) == "export enum") {
				$lineNum=parseEnum($lines,$lineNum);
				continue;
			}
			if (substr($line, 0, 6) == "export") {
				if (strpos($line,"=")!==false) {
					$line = str_replace(["export", "=", " ", ";"], "", $line);
					echo $indent . "class {$line} {}" . PHP_EOL;
					continue;
				}else{
					$lineNum = parseClass($lines, $lineNum);
					if ($obfw->isEnd()){
						break;
					}
					continue;
				}
			}

			
			$obfw->end();
			echo "2:undefined line {$actualFileName}:".($lineNum+1).":`{$line}`";
			break;
		}
		$indent = oudent();
		echo "}".PHP_EOL;
		return $lineNum;
	}
	function parseEnum($lines, $lineNum) {
		global $obfw;
		$indent = getIndent();
		$line = $lines[$lineNum++];
		$line = str_replace(["export","enum","{",], "", $line);
		$line = trim($line);

		$class = new ClassType($line);

		$enumNum=0;
		for (; $lineNum < count($lines); $lineNum++) {
			$line = trim($lines[$lineNum]);
			$line = trim(str_replace([","],"",$line));
			if ($line==""){
				continue;
			}
			
			if ($line=="}"){
				break;
			}

			$class->addConst($line,$enumNum++);
		}

		$class = $class->__toString();
		echo preg_replace("/^(.*)/m",$indent."$1",$class);

		return $lineNum;
	}
	function parseClass($lines, $lineNum) {
		/**
		 * @var OBFileWriter $obfw
		 */
		global $obfw;
		$indent = getIndent();
		$line = $lines[$lineNum++];
		if (($pos=strpos($line,"<"))!==false && ($lastPos=strpos($line,">"))!==false){
			$line=substr($line,0,$pos).substr($line,$lastPos+1);
		}
		$isInterface = strpos($line,"interface")!==false;
		$isExtend = strpos($line,"extends")!==false;
		$isImplements = strpos($line,"implements")!==false;
		$implementsTypes=[];
		if ($isImplements){
			$pos = strpos($line,"implements");
			$lastPos = strpos($line,"{");
			$implTypes = trim(substr($line,$pos+10,$lastPos-$pos+10));
			$implTypes = explode(",",$implTypes);
			foreach($implTypes as $type){
				$type=str_replace(["{"],"",$type);
				$implementsTypes[]=trim($type);
			}
			$line=substr($line,0,$pos);
		}
		$classType="";
		if ($isExtend){
			$pos = strpos($line,"extends");
			$lastPos = strpos($line,"implements");
			if ($lastPos===false){
				$lastPos = strpos($line,"{");
			}
			$classType=trim(substr($line,$pos+7,$lastPos-$pos+7));
			$classType=str_replace(["{"],"",$classType);
			$line=substr($line,0,$pos);
		}
		$line = str_replace(["export","declare", "class", "interface", "extends", "{", '"', "'"], "", $line);
		$line = trim($line);

		$class = new ClassType($line);
		if ($isInterface) {
			$class->setType('interface');
		}
		if ($isExtend){
			$class->addExtend(str_replace(".","\\",$classType));
		}
		if ($isImplements){
			foreach($implementsTypes as $type){
				$class->addImplement(str_replace(".","\\",$type));
			}
		}

		$currentComment=[];
		$currentCommentParams=[];
		$currentCommentReturn="";
		$knownMethods=[];
		for (; $lineNum < count($lines); $lineNum++) {
			$line = trim($lines[$lineNum]);

			if (strlen($line)==0){
				continue;
			}
			if ($line=="/**"){
				$currentComment=[];
				$currentCommentParams=[];
				$currentCommentReturn="";
				continue;
			}
			if ($line=="*/"){
				continue;
			}
			if (substr($line,0,1)=="*"){
				$line = trim(substr($line,1));
				if (substr($line,0,6)=="@param"){
					$line = trim(substr($line,6));
					$currentCommentParams[]=$line;
					continue;
				}
				if (substr($line,0,7)=="@return"){
					$line = trim(substr($line,7));
					$currentCommentReturn=$line;
					continue;
				}
				$currentComment[]=$line;
				continue;
			}
			if (substr($line,0,2)=="//"){
				continue;
			}
			
			$isStatic=false;

			if ($line=="}"){
				break;
			}


			if (substr($line,0,3)=="new"){
				continue;
			}
			if (substr($line,0,6)=="static"){
				$isStatic=true;
				$line=trim(substr($line,6));
			}
			$dvojbodkaPos = strpos($line,":");
			if (strpos($line,"(")===0){
				continue;
			}
			if ((($pos=strpos($line,"("))!==false && ($lastPos=strpos($line,")"))!==false) && ($dvojbodkaPos==false || $dvojbodkaPos>$pos) ){
				$funcName=substr($line,0,$pos);
				if ($funcName=="constructor"){
					$funcName="__construct";
				}
				$isUnableType=false;
				if (($ppos=strpos($funcName,"<"))!==false && ($llastPos=strpos($funcName,">"))!==false){
					$isUnableType=substr($funcName,$ppos+1,$ppos-$llastPos+1);
					$funcName=substr($funcName,0,$ppos);
				}
				if (in_array(strtolower($funcName),$knownMethods)){
					continue;
				}
				$knownMethods[]=strtolower($funcName);
				$method = $class->addMethod($funcName);
				$method->setStatic($isStatic);
				$params = substr($line,$pos+1,$lastPos-1-$pos);
				$params=str_replace([" ","?"],"",$params);
				$params=explode(",",$params);

				foreach($currentComment as $cmt){
					$method->addComment($cmt);
					$currentComment=[];
				}
				foreach($params as $paramPos=>$param){
					if ($param==""){
						break;
					}
					$paramComment = "@param ";
					$parts = explode(":",$param);
					$param=$parts[0];
					$isMultiple=false;
					if (substr($param,0,3)=="..."){
						$param=substr($param,3);
						$isMultiple=true;
					}
					$type=null;
					if (count($parts)>1){
						$type=$parts[1];
						if (substr($type,0,strlen($isUnableType))==$isUnableType){
							$type=str_replace($isUnableType,"mixed",$type);
						}
					}
					if ($type){
						$type=str_replace(".","\\",$type)." ";
						$type = str_replace(["any"],["mixed"],$type);
						$paramComment.=$type;
					}
					if ($isMultiple){
						$paramComment.="...";
					}
					$paramComment.='$'.$param;
					if (isset($currentCommentParams[$paramPos])){
						$paramComment.=" ".$currentCommentParams[$paramPos];
					}
					$currentCommentParams=[];
					$method->addComment($paramComment);
					$method->addParameter($param);
				}
				$returnComment="";
				if ($currentCommentReturn){
					$returnComment = "@return ";
				}
				$return = trim(substr($line,$lastPos+1));
				if (strpos($return,":")!==false){
					$return=str_replace([":",";"," "],"",$return);
					$return=str_replace($isUnableType,"mixed",$return);
					if (!$returnComment) {
						$returnComment = "@return ";
					}
					$return = str_replace(".", "\\", $return) . " ";
					$return = str_replace(["any"],["mixed"],$return);
					$returnComment .= $return;
				}
				if ($currentCommentReturn){
					$returnComment .= $currentCommentReturn;
					$currentCommentReturn="";
				}
				if ($returnComment){
					$method->addComment($returnComment);
				}
				continue;
			}
			$line = trim(str_replace([";"," "],"",$line));
			$parts = explode(":",$line);
			$property=$parts[0];
			$propertyType=null;

			$defaultValue=NULL;
			if (count($parts)>1){
				$propertyType=$parts[1];
				if (strpos($parts[count($parts)-1],"=>")!==false){
					$propertyType = "callable";
				}else {
					if (substr(trim($propertyType), 0, 1) == "{") {
						$propertyType="[]";
						list($lineNum, $defaultValue) = parseArray($lines, $lineNum);
						if ($obfw->isEnd()){
							return $lineNum;
						}

					}
				}
			}
			if ($isInterface){
				$comment = "@property ";
				if ($propertyType) {
					$comment .= str_replace(".", '\\', $propertyType)." ";
				}
				$comment.="\${$property} ";
				$comment.=join(" ",$currentComment);
				$currentComment=[];
				$class->addComment($comment);
			}else {
				if ($property=="" && $isStatic){
					$isStatic=false;
					$property="static";
				}
				$classProperty = $class->addProperty($property, $defaultValue);
				$classProperty->setStatic($isStatic);
				if (count($currentComment)) {
					foreach ($currentComment as $cmt) {
						$classProperty->addComment($cmt);
					}
					$currentComment = [];
				}
				if ($propertyType) {
					$classProperty->addComment("@var " . str_replace(".", '\\', $propertyType));
				}
			}
			continue;
		}
		
		$class = $class->__toString();
		echo preg_replace("/^(.*)/m",$indent."$1",$class);
		return $lineNum;
	}
	function parseArray($lines,$lineNum){
		/**
		 * @var OBFileWriter $obfw
		 */
		global $obfw,$actualFileName;
		$line=trim($lines[$lineNum++]);

		$array=[];
		if (trim(str_replace("{","",substr($line,strpos($line,"{"))))!=""){
			$line = str_replace(["{","}"],"",$line);
			$lines = explode(";",$line);
			foreach($lines as $line){
				if (trim($line)==""){
					continue;
				}
				list($key,$value) = explode(":",$line);
				$key=trim(str_replace(["?"],"",$key));
				$array[$key]=null;
			}
			return [$lineNum,$array];
		}

		for (; $lineNum < count($lines); $lineNum++) {
			$line=trim($lines[$lineNum]);
			if ($line=="};" || $line=="}"){
				break;
			}
			if (trim($line)==""){
				continue;
			}
			$parts = explode(":",$line);
			if (count($parts)!=2){
				$obfw->end();
				echo "3:undefined line {$actualFileName}:".($lineNum+1).":`{$line}`";
				return [$lineNum,$array];
			}
			list($key,$value) = $parts;
			$key=trim(str_replace(["?"],"",$key));
			$array[$key]=null;
		}


		return [$lineNum,$array];
	}

	function parseFunction($lines, $lineNum){
		/**
		 * @var OBFileWriter $obfw
		 */
		global $obfw,$actualFileName;
		$line = $lines[$lineNum++];
		$line = str_replace(["function","declare","export"],"",$line);
		$line = trim($line);
		$indent = getIndent();

		if ((($pos=strpos($line,"("))!==false && ($lastPos=strpos($line,")"))!==false)){
			$funcName=substr($line,0,$pos);
			$isUnableType=false;
			if (($ppos=strpos($funcName,"<"))!==false && ($llastPos=strpos($funcName,">"))!==false){
				$isUnableType=substr($funcName,$ppos+1,$ppos-$llastPos+1);
				$funcName=substr($funcName,0,$ppos);
			}
			$knownMethods[]=strtolower($funcName);
			$params = substr($line,$pos+1,$lastPos-1-$pos);
			$params=str_replace([" ","?"],"",$params);
			$params=explode(",",$params);

			$currentCommentParams=[];
			$currentParams=[];

			foreach($params as $paramPos=>$param){
				if ($param==""){
					break;
				}
				$paramComment = "@param ";
				$parts = explode(":",$param);
				$param=$parts[0];
				$isMultiple=false;
				if (substr($param,0,3)=="..."){
					$param=substr($param,3);
					$isMultiple=true;
				}
				$type=null;
				if (count($parts)>1){
					$type=$parts[1];
					if (substr($type,0,strlen($isUnableType))==$isUnableType){
						$type=str_replace($isUnableType,"mixed",$type);
					}
				}
				if ($type){
					$type=str_replace(".","\\",$type)." ";
					$type = str_replace(["any"],["mixed"],$type);
					$paramComment.=$type;
				}
				if ($isMultiple){
					$paramComment.="...";
				}
				$paramComment.='$'.$param;

				$currentCommentParams[]=$paramComment;
				$currentParams[]="\${$param}";
			}
			$returnComment="";

			$return = trim(substr($line,$lastPos+1));
			if (strpos($return,":")!==false){
				$return=str_replace([":",";"," "],"",$return);
				$return=str_replace($isUnableType,"mixed",$return);
				if (!$returnComment) {
					$returnComment = "@return ";
				}
				$return = str_replace(".", "\\", $return) . " ";
				$return = str_replace(["any"],["mixed"],$return);
				$returnComment .= $return;
			}

			if (count($currentCommentParams)>0 || $returnComment){
				echo "{$indent}/**".PHP_EOL;
				foreach($currentCommentParams as $comment){
					echo "{$indent} * ".$comment.PHP_EOL;
				}
				if ($returnComment){
					echo "{$indent} * ".$returnComment.PHP_EOL;
				}
				echo "{$indent} */".PHP_EOL;
			}
			echo "{$indent}function {$funcName}(";
			echo join(",",$currentParams);
			echo "){}".PHP_EOL;

		}else{

			$obfw->end();
			echo "4:undefined line {$actualFileName}:".($lineNum+1).":`{$line}`";
		}
		return $lineNum;
	}
}
