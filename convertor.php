<?php
namespace {


	use Nette\PhpGenerator\ClassType;

	require_once __DIR__ . "/vendor/autoload.php";
	require_once __DIR__ . "/lib/OBFileWriter.php";

	$obfw = new OBFileWriter(__DIR__ . '/php/phaser.comments.d.php');
	$obfw->start();

	$lines = file(__DIR__ . "/ts/phaser.comments.d.ts");

	$indentNum = 0;
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

	echo "<?php";
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
			$lineNum = parseModule($lines, $lineNum);
			continue;
		}
		if (substr($line, 0, 13) == "declare class") {
			$lineNum = parseClass($lines, $lineNum);
			continue;
		}
		$obfw->end();
		echo "1:undefined line ".($lineNum+1).":'{$line}'";
		break;
	}
	function parseModule($lines, $lineNum, $currentNamespace = []) {
		global $obfw,$indentNum;
		$indent = getIndent();
		$line = $lines[$lineNum++];
		$line = str_replace(["declare", "module", "{", '"', "'"], "", $line);
		$currentNamespace[] = trim($line);
		echo $indent . "namespace " . join("\\", $currentNamespace) . " {" . PHP_EOL;
		$indent = indent();
		for (; $lineNum < count($lines); $lineNum++) {
			$line = trim($lines[$lineNum]);
			if ($line==""){
				echo PHP_EOL;
				continue;
			}
			if ($line=="/**" || substr($line,0,1)=="*" || $line=="**/"){
				echo $indent.$line.PHP_EOL;
				continue;
			}

			if (substr($line, 0, 6) == "export") {
				$line = str_replace(["export", "=", " ", ";"], "", $line);
				echo $indent . "class {$line} {}" . PHP_EOL;
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
			if (substr($line, 0, 3) == "var") {
				$line=substr($line,4,-1);
				$parts = explode(":",$line);
				if (count($parts)==2 && trim($parts[1])=="Function"){
					if ($parts[0]=="Default"){
						$parts[0]="Default_";
					}
					echo $indent."function {$parts[0]}(){}".PHP_EOL;
					continue;
				}
			}
			
			$obfw->end();
			echo "2:undefined line ".($lineNum+1).":`{$line}`";
			break;
		}
		$indent = oudent();
		echo "}".PHP_EOL;
		return $lineNum;
	}

	function parseClass($lines, $lineNum) {
		global $obfw;
		$indent = indent();
		$line = $lines[$lineNum++];
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
			$line=substr($line,0,$pos);
		}
		$line = str_replace(["declare", "class", "interface", "extends", "{", '"', "'"], "", $line);
		$line = trim($line);

		$class = new ClassType($line);
		if ($isInterface) {
			$class->setType('interface');
		}
		if ($isExtend){
			$class->addExtend(str_replace(".","\\",$classType));
		}

		$currentComment=[];
		$currentCommentParams=[];
		$currentCommentReturn="";
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

			$isStatic=false;

			if ($line=="}"){
				break;
			}


			if (substr($line,0,6)=="static"){
				$isStatic=true;
				$line=trim(substr($line,6));
			}
			if (($pos=strpos($line,"("))!==false && ($lastPos=strpos($line,")"))!==false){
				$funcName=substr($line,0,$pos);
				if ($funcName=="constructor"){
					$funcName="__construct";
				}
				$isUnableType=false;
				if (($ppos=strpos($funcName,"<"))!==false && ($llastPos=strpos($funcName,">"))!==false){
					$isUnableType=substr($funcName,$ppos+1,$ppos-$llastPos+1);
					$funcName=substr($funcName,0,$ppos);
				}
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
						$paramComment.=str_replace(".","\\",$type)." ";
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
					$returnComment .= str_replace(".", "\\", $return) . " ";;
				}
				if ($currentCommentReturn){
					$returnComment .= " ".$currentCommentReturn;
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
			if (count($parts)>1){
				$propertyType=$parts[1];
			}

			$classProperty =$class->addProperty($property);
			$classProperty->setStatic($isStatic);
			if (count($currentComment)){
				foreach($currentComment as $cmt){
					$classProperty->addComment($cmt);
				}
				$currentComment=[];
			}
			if ($propertyType){
				$classProperty->addComment("@var ".str_replace(".",'\\',$propertyType));
			}
			continue;
		}
		
		$class = $class->__toString();
		echo preg_replace("/^(.*)/m",$indent."$1",$class);
		oudent();
		return $lineNum;
	}

	$obfw->end();
}
