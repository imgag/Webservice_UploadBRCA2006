<!------ header --------->
<script type="text/javascript" src="../WebservicesCommon/header.js"></script>

<!------ process --------->

<?php
include "../WebservicesCommon/functions.php";

//init
$ngsbits = get_path("ngs-bits");
$debug = false;

//arguments
$ps_name = $_POST['ps'];
list($s_name, $ps_id) = explode("_", $ps_name);

//get sample folder
list($stdout, $stderr, $exit_code) = exec2("{$ngsbits}NGSDExportSamples -sample {$s_name} -add_path SAMPLE_FOLDER | egrep '^#|_{$ps_name}' | {$ngsbits}TsvSlice -cols name,path");
if ($exit_code!=0)
{
	print "<p><font color=red>Error determining the sample folder<br>Exit code: ".$return_code."<br>Output: ".var_dump(array_merge($stdout, $stderr))."</font><br>";
	die;
}
list(,$folder) = explode("\t", $stdout[1]);

//check VCF exists
$vcf = "{$folder}/{$ps_name}_var.vcf.gz";
if ($debug) print "<br>VCF: $vcf</br>";
if (!file_exists($vcf))
{
	print "<font color=red>ERROR:</font> VCF file for sample '$ps_name' not found at default location '{$vcf}'!";
}
else
{
	//extract core variants
	$core_regions = "HBOC_13genes_hg38.bed";
	$tmp = temp_file(".vcf", $ps_name."_");
	execToolCpp("VariantFilterRegions", "-in $vcf -reg {$core_regions} -out $tmp", "Extracting variants in core region (".basename($core_regions).")");
	if ($debug) print "<br>VCF filtered: $tmp</br>";
	
	//annotate class of variants from NGSD
	$tmp2 = temp_file(".vcf", $ps_name."_");
	execToolCpp("VcfAnnotateFromVcf", "-in $tmp -annotation_file ".get_path("share_folder")."/data/dbs/NGSD/NGSD_germline.vcf.gz -info_ids CLAS -id_prefix NGSD -out $tmp2", "Annotating variants with NGSD classifications");
	if ($debug) print "<br>VCF annotated: $tmp2</br>";
			
	//extract variants
	$c_vars = 0;
	$c_class = 0;
	$output = array();
	$output[] = "##fileformat=VCFv4.2";
	$output[] = "##FORMAT=<ID=GT,Number=1,Type=String,Description=\"Genotype\">";
	$output[] = "##FORMAT=<ID=DP,Number=1,Type=Integer,Description=\"Read Depth\">";
	$output[] = "##FORMAT=<ID=AO,Number=A,Type=Integer,Description=\"Alternate allele observation count\">";
	$output[] = "##INFO=<ID=CLASS,Number=1,Type=String,Description=\"ACMG classification\">";
	$file = file($tmp2);
	foreach($file as $line)
	{
		$line = trim($line);
		if ($line=="") continue;
		
		//header
		if ($line[0]=="#")
		{
			if ($line[1]!="#")
			{
				$output[] = $line;
			}
			continue;
		}
		
		//skip off-target variants
		$parts = explode("\t", $line);
		if (contains($parts[6], "off-target")) continue;
		
		//format INFO field
		$parts = explode("\t", $line);
		$class = "";
		foreach(explode(";", $parts[7]) as $anno)
		{
			if (starts_with($anno, "NGSD_CLAS="))
			{
				$class = "CLASS=".substr($anno, -1);
				++$c_class;
				break;
			}
		}
		$parts[7] = $class;
		
		$output[] = implode("\t", $parts);	
		++$c_vars;
	}
	print "<p>Extracted {$c_vars} variants in core region for upload. {$c_class} of them have a classification.</a>";		
	
	//output
	$filename = "tmp/UploadBRCA2006_{$ps_name}_".date("ymd_His").".vcf";
	file_put_contents($filename, implode("\n", $output));
	
	print "<p><font color=red>NOTE: HerediCare VCF upload cannot be done until HG38 is completely implemented (Marc Sturm, 16.12.2021)</font>";
	//print "<p><a href=\"$filename\" download>[Download VCF file]</a>";		
}

add_back_button();

?>

<!------ footer --------->
<script type="text/javascript" src="../WebservicesCommon/footer.js"></script>
