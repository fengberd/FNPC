<?php
namespace FNPC\Utils;

/*
Copyright © 2016 FENGberd All right reserved.
GitHub Project:
https://github.com/fengberd/FNPC

This file using:
http://blog.csdn.net/uuq/article/details/6833753
*/

use ArrayObject;

class PNG
{
	/*
	PNG文件格式中的数据块
	数据块符号 数据块名称 多数据块 可选 位置限制
	IHDR 	文件头数据块 	否 	否 	第一块
	cHRM 	基色和白色点数据块 	否 	是 	在PLTE和IDAT之前
	gAMA 	图像γ数据块 	否 	是 	在PLTE和IDAT之前
	sBIT 	样本有效位数据块 	否 	是 	在PLTE和IDAT之前
	PLTE 	调色板数据块 	否 	是 	在IDAT之前
	bKGD 	背景颜色数据块 	否 	是 	在PLTE之后IDAT之前
	hIST 	图像直方图数据块 	否 	是 	在PLTE之后IDAT之前
	tRNS 	图像透明数据块 	否 	是 	在PLTE之后IDAT之前
	oFFs 	(专用公共数据块) 	否 	是 	在IDAT之前
	pHYs 	物理像素尺寸数据块 	否 	是 	在IDAT之前
	sCAL 	(专用公共数据块) 	否 	是 	在IDAT之前
	IDAT 	图像数据块 	是 	否 	与其他IDAT连续
	tIME 	图像最后修改时间数据块 	否 	是 	无限制
	tEXt 	文本信息数据块 	是 	是 	无限制
	zTXt 	压缩文本数据块 	是 	是 	无限制
	fRAc 	(专用公共数据块) 	是 	是 	无限制
	gIFg 	(专用公共数据块) 	是 	是 	无限制
	gIFt 	(专用公共数据块) 	是 	是 	无限制
	gIFx 	(专用公共数据块) 	是 	是 	无限制
	IEND 	图像结束数据 	否 	否 	最后一个数据块 
	*/
	private $png=null;
	private $fp=null;
	var $data=null;
	var $seek=0;
	var $info=array();
	var $color=array();
	const IHEADER='0D0A1A0A';//文件开头常量
	const IEND='0000000049454E44AE426082';//文件结尾常量
	
	public function __construct($png=null)
	{
		$this->png=$png;
		if($this->png!=null && $this->read($this->png)!==false)
		{
			$this->status=true;
		}
		else
		{
			$this->status=false;
		}
	}
	
	//读取1字节
	public function readBits8()
	{
		$t='';
		$t=unpack("C", fread($this->fp, 1));
		return $t[1];
	}
	
	//读取双字节
	public function readBH16()
	{
		$t='';
		$t=unpack("n", fread($this->fp, 2));
		return $t[1];
	}
	
	//读取双字节
	public function readBits16()
	{
		$t='';
		$t=unpack("S", fread($this->fp, 2));
		return $t[1];
	}
	
	//读取四字节
	public function readBH32()
	{
		$t='';
		$t=unpack("N", fread($this->fp, 4));
		return $t[1];
	}
	
	//读取四字节
	public function readBits32()
	{
		$t='';
		$t=unpack("L", fread($this->fp, 4));
		return $t[1];
	}
	
	public function version()
	{
		fseek($this->fp, 1);
		$this->info['version']=fread($this->fp, 3);
		return $this->info['version'];
	}
	
	public function seek($s)
	{
		fseek($this->fp, $s);
		return $this;
	}
	
	public function rBName()
	{
		return fread($this->fp, 4);
	}
	
	public function rSize($s)
	{
		return fread($this->fp, $s);
	}
	
	public function rCRC()
	{
		return $this->readBH32();
	}
	
	//读取一个块
	public function rBlock()
	{
		$b=new ArrayObject();
		$b->length=$this->readBH32();
		$b->name=$this->rBName();
		if ($b->length>0) {
			$b->data=$this->rSize($b->length);
		} else {
			$b->data='';
		}
		$b->crc=$this->rCRC();
		if (crc32($b->name . $b->data)==$b->crc) {
			$b->checkcrc=true;
		} else {
			$b->checkcrc=false;
		}
		return $b;
	}
	
	//IHDR文件头数据块
	public function pIHDR($obj)
	{
		//宽度四字节
		$t=unpack("N", substr($obj->data, 0, 4));
		$this->info['width']=$t[1];
		//高度四字节
		$t=unpack("N", substr($obj->data, 4, 4));
		$this->info['height']=$t[1];
		
		/*
		 *	Bit depth 	1 byte 	图像深度：
		 *	索引彩色图像：1，2，4或8
		 *	灰度图像：1，2，4，8或16
		 *	真彩色图像：8或16 
		 */
		$t=unpack("C", substr($obj->data, 8, 1));
		$this->info['bitdepth']=$t[1];
		
		/*
		 * ColorType 	1 byte 	颜色类型：
		 *	0：灰度图像, 1，2，4，8或16
		 *	2：真彩色图像，8或16
		 *	3：索引彩色图像，1，2，4或8
		 *	4：带α通道数据的灰度图像，8或16
		 *	6：带α通道数据的真彩色图像，8或16 
		 */
		$t=unpack("C", substr($obj->data, 9, 1));
		$this->info['colortype']=$t[1];
		
		//lz77的算法
		$t=unpack("C", substr($obj->data, 10, 1));
		$this->info['lz77']=$t[1];
		
		//滤波器方法 
		$t=unpack("C", substr($obj->data, 11, 1));
		$this->info['filter']=$t[1];
		
		//是否隔行扫描
		$t=unpack("C", substr($obj->data, 12, 1));
		$this->info['interlace']=$t[1];
		return $this;
	}
	
	//样本有效位数据块 
	public function psBit($obj)
	{
		$this->info['sbit']=array();
		for ($i=0; $i<$obj->length; $i++) {
			$t=unpack("C", substr($obj->data, $i, 1));
			$this->info['sbit'][$i]=$t[1];
		}
		return $this;
	}
	
	//调调色板读取
	public function pPLTE($obj)
	{
		$this->info['plte']=array();
		$j=0;
		for ($i=0; $i<$obj->length; $i+=3) {
			$r=unpack("C", substr($obj->data, $i, 1));
			$this->info['plte'][$j]['r']=$r[1];
			$g=unpack("C", substr($obj->data, $i+1, 1));
			$this->info['plte'][$j]['g']=$g[1];
			$b=unpack("C", substr($obj->data, $i+2, 1));
			$this->info['plte'][$j]['b']=$b[1];
			$this->info['plte'][$j]['rgb']=$this->rgbHex($r[1]) . $this->rgbHex($g[1]) . $this->rgbHex($b[1]);
			$j++;
		}
		return $this;
	}
	
	//图像透明数据块
	public function ptRNS($obj)
	{
		$this->info['trns']=array();
		for ($i=0; $i<$obj->length; $i++) {
			$t=unpack("C", substr($obj->data, $i, 1));
			$this->info['trns'][$i]=$t[1];
		}
		return $this;
	}
	
	//物理像素尺寸数据块
	public function ppHYs($obj)
	{
		$this->info['phys']=array();
		$t=unpack("N", substr($obj->data, 0, 4));
		$this->info['phys']['x']=$t[1];
		$t=unpack("N", substr($obj->data, 4, 4));
		$this->info['phys']['y']=$t[1];
		$t=unpack("C", substr($obj->data, 8, 1));
		$this->info['phys']['unit']=$t[1];
		return $this;
	}
	
	/*
	 * 文本信息数据块 
	 * 分隔行为02的话 是用自已的值去xor上面相同位置的值
	 * 00 1A50B8FF 000003FF 99CC33FF   
	 * 如果是01的话用后面的数据加上前边的RGB
	 * 用02数据RGB加上上面数据
	 * 03 为上一行数据除以2+本行数据 RGBA分别运算
	 * 如果不是第一列，那么本列前面的对应的字节加上一行对应的字节然后平均;
	 *
	 * 04 左边加上上边减去左上角，得到的值再减去（左边，上边，左上边 那个值最小就取那个。 
	 * 02 00000000 0000FC00 00000000
	 */
	public function ptEXt($obj)
	{
		$this->info['create']=$obj->data;
		return $this;
	}
	
	//图像数据块
	public function pIDAT($obj)
	{
		$this->data=gzuncompress($obj->data);
		$this->seek=0;
		return $this;
	}
	
	//物理像素尺寸数据块
	public function pcHRM($obj)
	{
		$this->info['chrm']=array();
		$t=unpack("N", substr($obj->data, 0, 4));
		$this->info['chrm']['wx']=$t[1];
		$t=unpack("N", substr($obj->data, 4, 4));
		$this->info['chrm']['wy']=$t[1];
		$t=unpack("N", substr($obj->data, 8, 4));
		$this->info['chrm']['rx']=$t[1];
		$t=unpack("N", substr($obj->data, 12, 4));
		$this->info['chrm']['ry']=$t[1];
		$t=unpack("N", substr($obj->data, 16, 4));
		$this->info['chrm']['gx']=$t[1];
		$t=unpack("N", substr($obj->data, 20, 4));
		$this->info['chrm']['gy']=$t[1];
		$t=unpack("N", substr($obj->data, 24, 4));
		$this->info['chrm']['bx']=$t[1];
		$t=unpack("N", substr($obj->data, 28, 4));
		$this->info['chrm']['by']=$t[1];
		return $this;
	}
	
	//读取文件信息
	public function read($png=null)
	{
		if ($this->png=null)
			$this->png=$png;
		$this->fp=fopen($png,'rb');
		if ($this->readBits8()==0x89 && $this->version()=='PNG' && $this->readBH32()==0x0D0A1A0A) {
			do {
				$obj=$this->rBlock();
				switch ($obj->name) {
					case 'IHDR':
						$this->pIHDR($obj);
						break;
					case 'sBIT':
						$this->psBit($obj);
						break;
					case 'PLTE':
						$this->pPLTE($obj);
						break;
					case 'tRNS':
						$this->ptRNS($obj);
						break;
					case 'pHYs':
						$this->ppHYs($obj);
						break;
					case 'cHRM':
						$this->pcHRM($obj);
						break;
					case 'tEXt':
						$this->ptEXt($obj);
						break;
					case 'IDAT':
						$this->pIDAT($obj);
						break;
					case 'IEND':
						break;
						
				}
			} while (!feof($this->fp) && $obj->name!='IEND');
			fclose($this->fp);
			return true;
		}
		else
		{
			return false;
		}
	}
	
	//解码文件
	function decode()
	{
		/*
		颜色类型：
		0：灰度图像, 1，2，4，8或16
		2：真彩色图像，8或16
		3：索引彩色图像，1，2，4或8
		4：带α通道数据的灰度图像，8或16
		6：带α通道数据的真彩色图像，8或16  
		*/
		switch ($this->info['colortype']) {
			case '0':
				break;
			case '2':
				$this->png24row();
				break;
			case '3':
				$this->indexrow();
				break;
			case '4':
				break;
			case '6':
				$this->png32row();
				break;
		}
		
	}
	
	//png32真彩读取
	function png32row()
	{
		$tcolor=array();
		$rh=array();
		for ($ii=0; $ii<$this->info['height']; $ii++) {
			$rh[$ii]=$this->rRowHeader();
			for ($i=0; $i<$this->info['width']; $i++) {
				$t=$this->rRGBA();
				$tcolor[$ii][$i]=$t;
				switch ($rh[$ii]) {
					case '0':
						$this->color[$ii][$i]=new ArrayObject();
						$this->color[$ii][$i]->r=$t->r;
						$this->color[$ii][$i]->g=$t->g;
						$this->color[$ii][$i]->b=$t->b;
						$this->color[$ii][$i]->a=$t->a;
						break;
					case '1':
						if ($i==0) {
							$this->color[$ii][$i]=new ArrayObject();
							$this->color[$ii][$i]->r=$t->r;
							$this->color[$ii][$i]->g=$t->g;
							$this->color[$ii][$i]->b=$t->b;
							$this->color[$ii][$i]->a=$t->a;
						} else {
							$this->color[$ii][$i]=new ArrayObject();
							$this->color[$ii][$i]->r=$this->filt1($t->r, $this->color[$ii][$i-1]->r);
							$this->color[$ii][$i]->g=$this->filt1($t->g, $this->color[$ii][$i-1]->g);
							$this->color[$ii][$i]->b=$this->filt1($t->b, $this->color[$ii][$i-1]->b);
							$this->color[$ii][$i]->a=$this->filt1($t->a, $this->color[$ii][$i-1]->a);
						}
						
						break;
					case '2':
						$this->color[$ii][$i]=new ArrayObject();
						$this->color[$ii][$i]->r=$this->filt2($t->r, $this->color[$ii-1][$i]->r);
						$this->color[$ii][$i]->g=$this->filt2($t->g, $this->color[$ii-1][$i]->g);
						$this->color[$ii][$i]->b=$this->filt2($t->b, $this->color[$ii-1][$i]->b);
						$this->color[$ii][$i]->a=$this->filt2($t->a, $this->color[$ii-1][$i]->a);
						break;
					case '3':
						if ($i==0) {
							$this->color[$ii][$i]=new ArrayObject();
							$this->color[$ii][$i]->r=$this->filt3($t->r, $this->color[$ii-1][$i]->r, 0);
							$this->color[$ii][$i]->g=$this->filt3($t->g, $this->color[$ii-1][$i]->g, 0);
							$this->color[$ii][$i]->b=$this->filt3($t->b, $this->color[$ii-1][$i]->b, 0);
							$this->color[$ii][$i]->a=$this->filt3($t->a, $this->color[$ii-1][$i]->a, 0);
						} else {
							$this->color[$ii][$i]=new ArrayObject();
							$this->color[$ii][$i]->r=$this->filt3($t->r, $this->color[$ii-1][$i]->r, $this->color[$ii][$i-1]->r);
							$this->color[$ii][$i]->g=$this->filt3($t->g, $this->color[$ii-1][$i]->g, $this->color[$ii][$i-1]->g);
							$this->color[$ii][$i]->b=$this->filt3($t->b, $this->color[$ii-1][$i]->b, $this->color[$ii][$i-1]->b);
							$this->color[$ii][$i]->a=$this->filt3($t->a, $this->color[$ii-1][$i]->a, $this->color[$ii][$i-1]->a);
						}
						break;
					case '4':
						if ($i==0) {
							$this->color[$ii][$i]=new ArrayObject();
							$this->color[$ii][$i]->r=$this->filt4($t->r, $this->color[$ii-1][$i]->r, $this->color[$ii-1][$i]->r, 0);
							$this->color[$ii][$i]->g=$this->filt4($t->g, $this->color[$ii-1][$i]->g, $this->color[$ii-1][$i]->g, 0);
							$this->color[$ii][$i]->b=$this->filt4($t->b, $this->color[$ii-1][$i]->b, $this->color[$ii-1][$i]->b, 0);
							$this->color[$ii][$i]->a=$this->filt4($t->a, $this->color[$ii-1][$i]->a, $this->color[$ii-1][$i]->a, 0);
						} else {
							$this->color[$ii][$i]=new ArrayObject();
							$this->color[$ii][$i]->r=$this->filt4($t->r, $this->color[$ii-1][$i]->r, $this->color[$ii][$i-1]->r, $this->color[$ii-1][$i-1]->r);
							$this->color[$ii][$i]->g=$this->filt4($t->g, $this->color[$ii-1][$i]->g, $this->color[$ii][$i-1]->g, $this->color[$ii-1][$i-1]->g);
							$this->color[$ii][$i]->b=$this->filt4($t->b, $this->color[$ii-1][$i]->b, $this->color[$ii][$i-1]->b, $this->color[$ii-1][$i-1]->b);
							$this->color[$ii][$i]->a=$this->filt4($t->a, $this->color[$ii-1][$i]->a, $this->color[$ii][$i-1]->a, $this->color[$ii-1][$i-1]->a);
						}
						break;
				}
			}
		}
	}
	
	//png24 真彩读取
	function png24row()
	{
		$tcolor=array();
		$rh=array();
		for ($ii=0; $ii<$this->info['height']; $ii++) {
			$rh[$ii]=$this->rRowHeader();
			for ($i=0; $i<$this->info['width']; $i++) {
				$t=$this->rRGB();
				$tcolor[$ii][$i]=$t;
				switch ($rh[$ii]) {
					case '0':
						$this->color[$ii][$i]=new ArrayObject();
						$this->color[$ii][$i]->r=$t->r;
						$this->color[$ii][$i]->g=$t->g;
						$this->color[$ii][$i]->b=$t->b;
						break;
					case '1':
						if ($i==0) {
							$this->color[$ii][$i]=new ArrayObject();
							$this->color[$ii][$i]->r=$t->r;
							$this->color[$ii][$i]->g=$t->g;
							$this->color[$ii][$i]->b=$t->b;
						} else {
							$this->color[$ii][$i]=new ArrayObject();
							$this->color[$ii][$i]->r=$this->filt1($t->r, $this->color[$ii][$i-1]->r);
							$this->color[$ii][$i]->g=$this->filt1($t->g, $this->color[$ii][$i-1]->g);
							$this->color[$ii][$i]->b=$this->filt1($t->b, $this->color[$ii][$i-1]->b);
						}
						
						break;
					case '2':
						$this->color[$ii][$i]=new ArrayObject();
						$this->color[$ii][$i]->r=$this->filt2($t->r, $this->color[$ii-1][$i]->r);
						$this->color[$ii][$i]->g=$this->filt2($t->g, $this->color[$ii-1][$i]->g);
						$this->color[$ii][$i]->b=$this->filt2($t->b, $this->color[$ii-1][$i]->b);
						break;
					case '3':
						if ($i==0) {
							$this->color[$ii][$i]=new ArrayObject();
							$this->color[$ii][$i]->r=$this->filt3($t->r, $this->color[$ii-1][$i]->r, 0);
							$this->color[$ii][$i]->g=$this->filt3($t->g, $this->color[$ii-1][$i]->g, 0);
							$this->color[$ii][$i]->b=$this->filt3($t->b, $this->color[$ii-1][$i]->b, 0);
						} else {
							$this->color[$ii][$i]=new ArrayObject();
							$this->color[$ii][$i]->r=$this->filt3($t->r, $this->color[$ii-1][$i]->r, $this->color[$ii][$i-1]->r);
							$this->color[$ii][$i]->g=$this->filt3($t->g, $this->color[$ii-1][$i]->g, $this->color[$ii][$i-1]->g);
							$this->color[$ii][$i]->b=$this->filt3($t->b, $this->color[$ii-1][$i]->b, $this->color[$ii][$i-1]->b);
						}
						break;
					case '4':
						if ($i==0) {
							$this->color[$ii][$i]=new ArrayObject();
							$this->color[$ii][$i]->r=$this->filt4($t->r, $this->color[$ii-1][$i]->r, $this->color[$ii-1][$i]->r, 0);
							$this->color[$ii][$i]->g=$this->filt4($t->g, $this->color[$ii-1][$i]->g, $this->color[$ii-1][$i]->g, 0);
							$this->color[$ii][$i]->b=$this->filt4($t->b, $this->color[$ii-1][$i]->b, $this->color[$ii-1][$i]->b, 0);
						} else {
							$this->color[$ii][$i]=new ArrayObject();
							$this->color[$ii][$i]->r=$this->filt4($t->r, $this->color[$ii-1][$i]->r, $this->color[$ii][$i-1]->r, $this->color[$ii-1][$i-1]->r);
							$this->color[$ii][$i]->g=$this->filt4($t->g, $this->color[$ii-1][$i]->g, $this->color[$ii][$i-1]->g, $this->color[$ii-1][$i-1]->g);
							$this->color[$ii][$i]->b=$this->filt4($t->b, $this->color[$ii-1][$i]->b, $this->color[$ii][$i-1]->b, $this->color[$ii-1][$i-1]->b);
						}
						break;
				}
			}
		}
	}
	
	//索引色读取一行
	function indexrow()
	{
		for ($ii=0; $ii<$this->info['height']; $ii++) {
			$rowchar=ceil($this->info['width']*($this->info['bitdepth'])/8);
			$rowheader=$this->rRowHeader();
			//一行的偏移
			$this->wseek=0;
			$rowdata=substr($this->data, $this->seek, $rowchar);
			$this->seek+=$rowchar;
			$cdata='';
			for ($i=strlen($rowdata)-1; $i>=0; $i--) {
				$t=unpack("C", $rowdata[$i]);
				$cdata=str_pad(decbin($t[1]), 8, "0", STR_PAD_LEFT) . $cdata;
			}
			for ($i=0; $i<$this->info['width']; $i++) {
				$this->color[$ii][$i]=new ArrayObject();
				$this->color[$ii][$i]->r=$this->info['plte'][bindec(substr($cdata, $this->wseek, $this->info['bitdepth']))]['r'];
				$this->color[$ii][$i]->g=$this->info['plte'][bindec(substr($cdata, $this->wseek, $this->info['bitdepth']))]['g'];
				$this->color[$ii][$i]->b=$this->info['plte'][bindec(substr($cdata, $this->wseek, $this->info['bitdepth']))]['b'];
				$this->wseek+=$this->info['bitdepth'];
			}
			
		}
	}
	
	/*
	 * 读取每行分隔
	 * 32 24 位png时候可能会有 01 02 03 04等目前还不知道是什么
	 * 别的程序生成没有，但是firework生成有，可能跟透明度有关和重复有关
	 */
	function rRowHeader()
	{
		$t=unpack("C", substr($this->data, $this->seek, 1));
		$this->seek+=1;
		return $t[1];
	}
	
	function rBits()
	{
		$t=unpack("C", substr($this->data, $this->seek, 1));
		$this->seek+=1;
		return $t[1];
	}
	
	function rBitC()
	{
		$t=unpack("n", substr($this->data, $this->seek, 2));
		$this->seek+=2;
		return $t[1];
	}
	
	function rRGB()
	{
		$rgb=new ArrayObject();
		$t=unpack("C", substr($this->data, $this->seek, 1));
		$this->seek+=1;
		$rgb->r=$t[1];
		$t=unpack("C", substr($this->data, $this->seek, 1));
		$this->seek+=1;
		$rgb->g=$t[1];
		$t=unpack("C", substr($this->data, $this->seek, 1));
		$this->seek+=1;
		$rgb->b=$t[1];
		
		return $rgb;
	}
	
	function rRGBA()
	{
		$rgb=new ArrayObject();
		$t=unpack("C", substr($this->data, $this->seek, 1));
		$this->seek+=1;
		$rgb->r=$t[1];
		$t=unpack("C", substr($this->data, $this->seek, 1));
		$this->seek+=1;
		$rgb->g=$t[1];
		$t=unpack("C", substr($this->data, $this->seek, 1));
		$this->seek+=1;
		$rgb->b=$t[1];
		$t=unpack("C", substr($this->data, $this->seek, 1));
		$this->seek+=1;
		$rgb->a=$t[1];
		
		return $rgb;
	}
	
	//行头为01类形
	function filt1($x, $b)
	{
		$p=$x+$b;
		$p=substr(str_pad(strtoupper(dechex($p)), 2, "0", STR_PAD_LEFT), -2);
		
		return hexdec('0x' . $p);
	}
	
	//行头为02类形
	function filt2($x, $b)
	{
		$p=$x+$b;
		$p=substr(str_pad(strtoupper(dechex($p)), 2, "0", STR_PAD_LEFT), -2);
		return hexdec('0x' . $p);
	}
	
	//行头为03类形
	function filt3($x, $b, $a=0)
	{
		$p=($b+$a)/2;
		$p=$x+$p;
		$p=substr(str_pad(strtoupper(dechex($p)), 2, "0", STR_PAD_LEFT), -2);
		return hexdec('0x' . $p);
	}
	
	//行头为04类形
	function filt4($x, $b, $a=0, $c=0)
	{
		$p=$a+$b-$c;
		$pa=abs($p-$a);
		$pb=abs($p-$b);
		$pc=abs($p-$c);
		if ($pa<$pb && $pa<=$pc)
			$p=$a;
		elseif ($pb<=$pc)
			$p=$b;
		else
			$p=$c;
		$p=$x+$p;
		
		$p=substr(str_pad(strtoupper(dechex($p)), 2, "0", STR_PAD_LEFT), -2);
		return hexdec('0x' . $p);
	}
	
	function rgbHex($value)
	{
		$value&=0xff;
		return str_pad(strtoupper(dechex($value)), 2, "0", STR_PAD_LEFT);
	}
	
	function tohex($value)
	{
		$value&=0xffffffff;
		return str_pad(strtoupper(dechex($value)), 8, "0", STR_PAD_LEFT);
	}
}
?>
