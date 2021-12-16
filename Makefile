help:
	
roi:
	echo 'ATM\nBARD1\nBRCA1\nBRCA2\nBRIP1\nCDH1\nCHEK2\nPALB2\nPTEN\nRAD51C\nRAD51D\nSTK11\nTP53' | GenesToBed -mode exon -source ensembl | BedExtend -n 20 > HBOC_13genes_hg38.bed