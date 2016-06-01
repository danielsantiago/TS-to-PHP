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
		echo "1:undefined line '{$line}'";
		break;
	}
	function parseModule($lines, $lineNum, $currentNamespace = []) {
		global $obfw;
		$indent = getIndent();
		$line = $lines[$lineNum++];
		$line = str_replace(["declare", "module", "{", '"', "'"], "", $line);
		$currentNamespace[] = trim($line);
		echo $indent . "namespace " . join("\\", $currentNamespace) . " {" . PHP_EOL;
		$indent = indent();
		for (; $lineNum < count($lines); $lineNum++) {
			$line = trim($lines[$lineNum]);

			if (substr($line, 0, 6) == "export") {
				$line = str_replace(["export", "=", " ", ";"], "", $line);
				echo $indent . "class {$line} {}" . PHP_EOL;
				continue;
			}
			if (trim($line) == "}") {
				break;
			}

			$obfw->end();
			echo "2:undefined line '{$line}'";
			break;
		}
		$indent = oudent();
		echo "}";
		return $lineNum;
	}

	function parseClass($lines, $lineNum) {
		global $obfw;
		$indent = getIndent();
		$line = $lines[$lineNum++];
		$line = str_replace(["declare", "class", "{", '"', "'"], "", $line);
		$line = trim($line);

		$class = new ClassType($line);

		$currentComment=[];
		$currentCommentParams=[];
		$currentCommentReturn="";
		for (; $lineNum < count($lines); $lineNum++) {
			$line = trim($lines[$lineNum++]);

			if (strlen($line)==0){
				continue;
			}
			if ($line=="/**"){
				$currentComment=[];
				$currentCommentParams=[];
				$currentCommentReturn="";
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
			}
			if ($line=="**/"){
				continue;
			}

			$isStatic=false;

			if (substr($line,0,6)=="static"){
				$isStatic=true;
				$line=trim(substr($line,6));
			}
			if (($pos=strpos($line,"("))!==false && ($lastPos=strpos($line,")"))!==false){
				$funcName=substr($line,0,$pos);
				if ($funcName=="constructor"){
					$funcName="__construct";
				}
				$method = $class->addMethod($funcName);
				$method->setStatic($isStatic);
				$params = substr($line,$pos+1,$lastPos-1);
				$params=str_replace([" ","?"],"",$params);
				$params=explode(",",$params);

				foreach($currentComment as $cmt){
					$method->addComment($cmt);
				}
				foreach($params as $paramPos=>$param){
					$paramComment = "@param ";
					$parts = explode(":",$param);
					$param=$parts[0];
					$type=null;
					if (count($parts)>1){
						$type=$parts[1];
					}
					if ($type){
						$paramComment.=str_replace(".","\\",$type)." ";
					}
					$paramComment.='$'.$param;
					if (isset($currentCommentParams[$paramPos])){
						$paramComment.=" ".$currentCommentParams[$paramPos];
					}
					$method->addComment($paramComment);
					$method->addParameter($param);
				}
				$returnComment="";
				if ($currentCommentReturn){
					$returnComment = "@return ";
				}
				$return = substr($line,$lastPos);
				if (strpos($return,":")!==false){
					$return=str_replace([":",";"],"",$return);
					if (!$returnComment){
						$returnComment = "@return ";
					}
					$returnComment .= str_replace(".","\\",$return)." ";;
				}
				if ($currentCommentReturn){
					$returnComment .= " ".$currentCommentReturn;
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
			if (count($currentComment)){
				foreach($currentComment as $cmt){
					$classProperty->addComment($cmt);
				}
			}
			if ($propertyType){
				$classProperty->addComment("@var ".str_replace(".",'\\',$propertyType));
			}
			continue;

			$obfw->end();
			echo "3:undefined line '{$line}'";
			break;
		}
		
		echo $class->__toString();
		
		return $lineNum;
	}

	$obfw->end();
}