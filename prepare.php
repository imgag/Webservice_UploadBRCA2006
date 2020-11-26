<!------ header --------->
<script type="text/javascript" src="../WebservicesCommon/header.js"></script>

<!------ process --------->

<?php
include "../WebservicesCommon/functions.php";

$ps_name = $_POST['ps'];
list($s_name, $ps_id) = explode("_", $ps_name);

//check sample exists
$db = DB::getInstance("ngsd");
$res = $db->executeQuery("SELECT p.type, p.name FROM project p, sample s, processed_sample ps WHERE p.id=ps.project_id AND s.id=ps.sample_id AND s.name='{$s_name}' AND ps.process_id={$ps_id}"); 
if (count($res)==0)
{
	print "<font color=red>ERROR:</font> Unknown sample '$ps_name'!";
}
else
{
	//check VCF exists
	$vcf = get_path("project_folder")."/".$res[0]['type']."/".$res[0]['name']."/Sample_{$ps_name}/{$ps_name}_var.vcf.gz";
	if (!file_exists($vcf))
	{
		print "<font color=red>ERROR:</font> VCF file for sample '$ps_name' not found at default location!";
	}
	else
	{
		//extract core variants
		$core_regions = "/mnt/share/data/enrichment/subpanels/HBOC_13genes_20_exon20_ahstaea1_20190625.bed";
		$tmp = temp_file(".vcf", $ps_name."_");
		execToolCpp("VariantFilterRegions", "-in $vcf -reg {$core_regions} -out $tmp", "Extracting variants in core region (".basename($core_regions).")");
		
		//annotate class of variants from NGSD
		$tmp2 = temp_file(".vcf", $ps_name."_");
		execToolCpp("VcfAnnotateFromVcf", "-in $tmp -annotation_file /mnt/share/data/dbs/NGSD/NGSD_germline.vcf.gz -info_ids CLAS -id_prefix NGSD -out $tmp2", "Annotating variants with NGSD classifications");
				
		//extract variants
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
					break;
				}
			}
			$parts[7] = $class;
			
			$output[] = implode("\t", $parts);	
		}
		
		//output
		$filename = "tmp/UploadBRCA2006_{$ps_name}_".date("ymd_His").".vcf";
		file_put_contents($filename, implode("\n", $output));
		print "<br><a href=\"$filename\" download>[Download VCF file]</a>";		
	}
}

add_back_button();

?>

<!------ footer --------->
<script type="text/javascript" src="../WebservicesCommon/footer.js"></script>
