<?php

require_once('twitteroauth/twitteroauth.php');

class Sokoban{

	const WALL = 0;
	const FLOOR = 1;
	const GOAL = 2;
	const OBJECT = 3;
	const OBJ_ON_GOAL = 4;
	const PLAYER = 5;
	const PLAY_ON_GOAL = 6;
	
	private $width;
	private $height;
	
	private $field;
	
	private $x;
	private $y;
	
	private $goalNum;
	
	function __construct($filename){
		$this->stageSet($filename);
	}
	
	public function stageSet($filename){
		$this->x = 0;
		$this->y = 0;
		$this->goalNum = 0;
		
		$hFile = fopen($filename, 'r');
		
		$this->width = -1;
		$this->height = 0;
		while($line = fgets($hFile)){
			$this->width = max($this->width, strlen($line) - 2);
			$this->height++;
		}
		
		fseek($hFile, SEEK_SET);
		for($y = 0;$y < $this->height;$y++){
			$x = 0;
			for(;($char = fgetc($hFile)) !== "\n";$x++){
				$cell = 0;
				if($char === '#'){
					$cell = self::WALL;
				}
				elseif($char === ' '){
					$cell = self::FLOOR;
				}
				elseif($char === '.'){
					$this->goalNum++;
					$cell = self::GOAL;
				}
				elseif($char === '?'){
					$cell = self::OBJECT;
				}
				elseif($char === '!'){
					$this->goalNum++;
					$cell = self::OBJ_ON_GOAL;
				}
				elseif($char === '*'){
					$this->x = $x;
					$this->y = $y;
					$cell = self::PLAYER;
				}
				elseif($char === '@'){
					$this->x = $x;
					$this->y = $y;
					$cell = self::PLAY_ON_GOAL;
				}
				$this->field[$y][$x] = $cell;
			}
			for(;$x < $this->width;$x++){
				$this->field[$y][$x] = WALL;
			}
		}
		
		fclose($hFile);
	}
	
	private function isEnabledPos($x, $y){
		$isIn = (0 <= $x && $x < $this->width && 0 <= $y && $y < $this->height);
		$canOver = ($this->field[$y][$x] == self::FLOOR || $this->field[$y][$x] == self::GOAL);
		return ($isIn && $canOver);
	}
	
	public function move($dx, $dy){
		$tx = $this->x + $dx;
		$ty = $this->y + $dy;
		if(!$this->isEnabledPos($tx, $ty) && $this->field[$ty][$tx] == self::WALL) return;
		
		if($this->field[$ty][$tx] == self::OBJECT || $this->field[$ty][$tx] == self::OBJ_ON_GOAL){
			$tx2 = $tx + $dx;
			$ty2 = $ty + $dy;
			if(!$this->isEnabledPos($tx2, $ty2)) return;
			
			$this->field[$ty2][$tx2] = ($this->field[$ty2][$tx2] != self::GOAL)? self::OBJECT : self::OBJ_ON_GOAL;
			$this->field[$ty][$tx] = ($this->field[$ty][$tx] == self::OBJECT)? self::FLOOR : self::GOAL;
		}
		
		$this->field[$this->y][$this->x] = ($this->field[$this->y][$this->x] == self::PLAYER)? self::FLOOR : self::GOAL;
		$this->field[$ty][$tx] = ($this->field[$ty][$tx] != self::GOAL)? self::PLAYER : self::PLAY_ON_GOAL;
		
		$this->x = $tx;
		$this->y = $ty;
	}
	
	public function isClear(){
		$num = 0;
		foreach($this->field as $line){
			foreach($line as $cell){
				if($cell == self::OBJ_ON_GOAL) $num++;
			}
		}
		return ($num == $this->goalNum);
	}
	
	public function getField(){
		return $this->field;
	}
}

#change it
$c_key = '';
$c_secret = '';
$a_token = '';
$a_token_secret = '';

$twitter = new TwitterOAuth($c_key, $c_secret, $a_token, $a_token_secret);
#$twitter->format = 'xml';

$sokoban = new Sokoban('temp.txt');

$timeline = $twitter->get('statuses/mentions', array('count'=>100));

$conf = simplexml_load_file('config.xml');

#unix time
$lasttime = intval($conf->LastTweet);

#上下左右詰
$votes = array(0, 0, 0, 0, 0);
$voteNum = 0;
$table = array('上'=>0, '下'=>1, '左'=>2, '右'=>3, '詰'=>4);

foreach($timeline as $value){
	if(strtotime($value->created_at) <= $lasttime) continue;
	
	$count = 0;
	$idx = 0;
	foreach($table as $key => $val){
		if(strpos($value->text, $key) !== false){
			$idx = $val;
			$count++;
		}
	}
	if($count === 1){
		$votes[$idx]++;
		$voteNum++;
	}
}

if($voteNum > 0){

	$maxval = -1;
	$maxidx = 0;
	for($i = 0;$i < count($votes);$i++){
		if($votes[$i] > $maxval){
			$maxval = $votes[$i];
			$maxidx = $i;
		}
	}

	$dx = 0;
	$dy = 0;
	if($maxidx === 0) $dy = -1;
	if($maxidx === 1) $dy = 1;
	if($maxidx === 2) $dx = -1;
	if($maxidx === 3) $dx = 1;
	if($maxidx === 4){
		#詰
		$sokoban->stageSet('stage.txt');
	}
	$sokoban->move($dx, $dy);

}

$field = $sokoban->getField();
$table = array('■', '□', '・', '？', '！', '◆', '◇');
$msg = '';
foreach($field as $line){
	foreach($line as $cell){
		$msg = $msg.$table[$cell];
	}
	$msg = $msg."\n";
}

$msg = $msg.sprintf("上:%d 下:%d 左:%d 右:%d 詰:%d", $votes[0], $votes[1], $votes[2], $votes[3], $votes[4]);

$req = $twitter->post('statuses/update', array('status'=>$msg));
$conf->LastTweet = strval(strtotime($req->created_at));
$conf->asXML('config.xml');

$table = array('#', ' ', '.', '?', '!', '*', '@');
$hFile = fopen('temp.txt', 'w');
foreach($field as $line){
	foreach($line as $cell){
		fwrite($hFile, $table[$cell], strlen($table[$cell]));
	}
	fwrite($hFile, "\n", strlen("\n"));
}

?>
