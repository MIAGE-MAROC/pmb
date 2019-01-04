<?php
// +-------------------------------------------------+
// � 2002-2005 PMB Services / www.sigb.net pmb@sigb.net et contributeurs (voir www.sigb.net)
// +-------------------------------------------------+
// $Id: quotas.class.php,v 1.31 2018-12-20 14:00:06 mbertin Exp $

if (stristr($_SERVER['REQUEST_URI'], ".class.php")) die("no access");

//Classe de calcul des quotas

require_once($include_path."/parser.inc.php");
require_once($class_path."/marc_table.class.php");

class quota {
	
	public $type_id;
	public $quota_type;
	public $elements_values_id;
	public $elements_values;
	public $elements_forc_id;
	public $elements_forc;
	public $error_message;
	public $force;
	public $table;
	public $descriptor;
	public static $_quotas_;
	
	//Constructeur
	public function __construct($type_id="",$descriptor="") {
		global $lang;
		global $include_path;
	
		if ($descriptor=="") $this->descriptor=$include_path."/quotas/$lang.xml"; else $this->descriptor=$descriptor;
	
		if(!isset(static::$_quotas_[$this->descriptor])) {
			static::parse_quotas($this->descriptor);
		}
		$this->table=static::$_quotas_[$this->descriptor]['_table_'];
		 
		if ($type_id!="") {
			$this->type_id=$type_id;
			$this->quota_type=$this->get_quota_type_by_id($type_id);
			if (count($this->quota_type)==0) $this->quota_type=$this->get_quota_type_by_name($type_id);
		}
	}
	
	public static function parse_quotas($descriptor="") {
// 		global $_parsed_quotas_;
		global $_quotas_elements_;
		global $_quotas_types_;
		global $lang;
		global $include_path;
		global $_quotas_table_;
		
		//Recherche du fichier de description
		if ($descriptor) {
			$p_descriptor=$descriptor;
		} else {
			$p_descriptor=$include_path."/quotas/$lang.xml";
		}
		
		// Gestion de fichier subst	
		$p_descriptor_subst=substr($p_descriptor,0,-4)."_subst.xml";
		if (file_exists($p_descriptor_subst)) {
			$p_descriptor=$p_descriptor_subst;
		}
		if(!isset(static::$_quotas_[$descriptor])) {
			static::$_quotas_[$descriptor] = array();
			//Parse le fichier dans un tableau
			$fp=fopen($p_descriptor,"r") or die("Can't find XML file $p_descriptor");
			$xml=fread($fp,filesize($p_descriptor));
			fclose($fp);
			$param=_parser_text_no_function_($xml, "PMBQUOTAS");
			
			if (!isset($param["TABLE"])) {
				$table="quotas";
			} else  {
				$table=$param["TABLE"];
			}
			static::$_quotas_[$descriptor]['_table_'] = $table;
			
			//R�cup�ration des �l�ments
			for ($i=0; $i<count($param["ELEMENTS"][0]["ELEMENT"]); $i++) {
				$p_elt=$param["ELEMENTS"][0]["ELEMENT"][$i];
				$elt=array();
				$elt["NAME"]=$p_elt["NAME"];
				$elt["ID"]=$p_elt["ID"];
				$elt["COMMENT"]=$p_elt["COMMENT"];
				$elt["LINKEDTO"]=$p_elt["LINKEDTO"][0]["value"];
				$elt["TABLELINKED"]=$p_elt["TABLELINKED"][0]["value"];
				$elt["TABLELINKED_BY"]=(isset($p_elt["TABLELINKED"][0]["BY"]) ? $p_elt["TABLELINKED"][0]["BY"] : '');
				$elt["LINKEDFIELD"]=$p_elt["LINKEDFIELD"][0]["value"];
				$elt["LINKEDID"]=$p_elt["LINKEDID"][0]["value"];
				$elt["LINKEDID_BY"]=(isset($p_elt["LINKEDID"][0]["BY"]) ? $p_elt["LINKEDID"][0]["BY"] : '');
				$elt["TABLE"]=$p_elt["TABLE"][0]["value"];
				if (isset($p_elt["TABLE"][0]["TYPE"]) && ($p_elt["TABLE"][0]["TYPE"]=="marc_list")) {
					$ml=new marc_list($elt["TABLE"]);
					reset($ml->table);
					$requete="create temporary table ".$elt["TABLE"]." (id varchar(255),libelle varchar(255)) ENGINE=MyISAM ";
					pmb_mysql_query($requete);
					while (list($key,$val)=each($ml->table)) {
						$requete="insert into ".$elt["TABLE"]." (id,libelle) values('".addslashes($key)."','".addslashes($val)."')";
						pmb_mysql_query($requete);
					}
					$elt["FIELD"]="id";
					$elt["LABEL"]="libelle";
				} else {
					$elt["FIELD"]=$p_elt["FIELD"][0]["value"];
					$elt["LABEL"]=$p_elt["LABEL"][0]["value"];
				}
				if(!defined($elt["NAME"])) {
					define($elt["NAME"],$elt["ID"]);
				}
				static::$_quotas_[$descriptor]['_elements_'][]=$elt;
			}
			
			//R�cup�ration des types
			for ($i=0; $i<count($param["TYPES"][0]["TYPE"]); $i++) {
				$p_typ=$param["TYPES"][0]["TYPE"][$i];
				$typ=array();
				$typ["NAME"]=$p_typ["NAME"];
				$typ["ID"]=$p_typ["ID"];
				$typ["COMMENT"]=$p_typ["COMMENT"];
				$typ["SHORT_COMMENT"]=$p_typ["SHORT_COMMENT"];
				$typ["COMMENTFORCELEND"]=(isset($p_typ["COMMENTFORCELEND"]) ? $p_typ["COMMENTFORCELEND"] : '');
				$typ["FILTER_ID"]=(isset($p_typ["FILTER_ID"]) ? $p_typ["FILTER_ID"] : '');
				$typ["SPECIALCLASS"]=(isset($p_typ["SPECIALCLASS"]) ? $p_typ["SPECIALCLASS"] : '');
				$typ["DEFAULT_VALUE_LABEL"]=(isset($p_typ["DEFAULT_VALUE_LABEL"]) ? $p_typ["DEFAULT_VALUE_LABEL"] : '');
				if(isset($p_typ["CONFLIT_MAX"])) {
					$typ["CONFLIT_MAX"]=($p_typ["CONFLIT_MAX"] == "no" ? false : true);
				} else {
					$typ["CONFLIT_MAX"]='';
				}
				if(isset($p_typ["CONFLIT_MIN"])) {
					$typ["CONFLIT_MIN"]=($p_typ["CONFLIT_MIN"] == "no" ? false : true);
				} else {
					$typ["CONFLIT_MIN"]='';
				}
				$typ["ELEMENTS_LABEL"]=(isset($p_typ["ELEMENTS_LABEL"]) ? $p_typ["ELEMENTS_LABEL"] : '');
		
				if(isset($p_typ["ENTITY"])) {
					$p_typ_entity=$p_typ["ENTITY"][0];
					$typ["ENTITY"]=$p_typ_entity["NAME"];
					if ($p_typ_entity["MAXQUOTA"]=="yes") $typ["MAX_QUOTA"]=true;
					
					$typ["COUNT_TABLE"]=$p_typ_entity["COUNTTABLE"][0]["value"];
					$typ["COUNT_FIELD"]=$p_typ_entity["COUNTFIELD"][0]["value"];
					$typ["COUNT_FILTER"]=$p_typ_entity["COUNTFILTER"][0]["value"];
					$typ["MAX_ERROR_MESSAGE"]=$p_typ_entity["MAX_ERROR_MESSAGE"][0]["value"];
					$typ["PARTIAL_ERROR_MESSAGE"]=$p_typ_entity["PARTIAL_ERROR_MESSAGE"][0]["value"];
					$typ["DEFAULT_ERROR_MESSAGE"]=$p_typ_entity["DEFAULT_ERROR_MESSAGE"][0]["value"];
				} else {
					$typ["ENTITY"]='';
					$typ["MAX_QUOTA"]='';
					
					$typ["COUNT_TABLE"]='';
					$typ["COUNT_FIELD"]='';
					$typ["COUNT_FILTER"]='';
					$typ["MAX_ERROR_MESSAGE"]='';
					$typ["PARTIAL_ERROR_MESSAGE"]='';
					$typ["DEFAULT_ERROR_MESSAGE"]='';
				}
				
				if ($p_typ["MAX"]=="yes") $typ["MAX"]=true; else $typ["MAX"]=false;
				if ($p_typ["MIN"]=="yes") $typ["MIN"]=true; else $typ["MIN"]=false;
				if ($p_typ["FORCELEND"]=="yes") $typ["FORCELEND"]=true; else $typ["FORCELEND"]=false;
		
				$quotas=array();
				$countfields=array();
				for ($j=0; $j<count($p_typ["QUOTAS"][0]["ON"]); $j++) {
					$quotas[]=$p_typ["QUOTAS"][0]["ON"][$j]["value"];
					if(isset($p_typ["QUOTAS"][0]["ON"][$j]["COUNTFIELDS"])) {
						$countfields[]=$p_typ["QUOTAS"][0]["ON"][$j]["COUNTFIELDS"];
					} else {
						$countfields[]='';
					}
				}
				$typ["QUOTAS"]=$quotas;
				$typ["COUNTFIELDS"]=$countfields;
		
				define($typ["NAME"],$typ["ID"]);
				static::$_quotas_[$descriptor]['_types_'][]=$typ;
			}
		}
		//provisoire
		$_quotas_table_ = static::$_quotas_[$descriptor]['_table_'];
		$_quotas_elements_ = static::$_quotas_[$descriptor]['_elements_'];
		$_quotas_types_ = static::$_quotas_[$descriptor]['_types_'];
		
// 		$_parsed_quotas_=1;
	}
	
	//R�cup�ration d'un �l�ment � partir de son nom
	public function get_element_by_name($element_name) {
		if (isset(static::$_quotas_[$this->descriptor])) {
			reset(static::$_quotas_[$this->descriptor]['_elements_']);
			while (list($key,$val)=each(static::$_quotas_[$this->descriptor]['_elements_'])) {
				if ($val["NAME"]==$element_name)
					return $key;
			}
			return -1;
		} else return -1;
	}
	
	//R�cup�ration d'un �l�ment � partir de son ID
	public function get_element_by_id($element_id) {
		if (isset(static::$_quotas_[$this->descriptor])) {
			reset(static::$_quotas_[$this->descriptor]['_elements_']);
			while (list($key,$val)=each(static::$_quotas_[$this->descriptor]['_elements_'])) {
				if ($val["ID"]==$element_id)
					return $key;
			}
			return -1;
		} else return -1;
	}
	
	//R�cup�ration de l'ID d'un �l�ment par son nom
	public function get_element_id_by_name($element_name) {
		if (isset(static::$_quotas_[$this->descriptor])) {
			reset(static::$_quotas_[$this->descriptor]['_elements_']);
			while (list($key,$val)=each(static::$_quotas_[$this->descriptor]['_elements_'])) {
				if ($val["NAME"]==$element_name)
					return $val["ID"];
			}
			return -1;
		} else return -1;
	}
	
	//R�cup�ration de l'ID de plusieurs �l�ments par leur noms s�par�s par des virgules
	public function get_elements_id_by_names($elements) {
		$id=0;
		$elts=explode(",",$elements);
		for ($j=0; $j<count($elts); $j++) {
			$id|=$this->get_element_id_by_name($elts[$j]);
		}
		
		return $id;
	}
	
	//R�cup�ration de la structure type de quota par son id
	public function get_quota_type_by_id($type_id) {
		$r=array();
		if (isset(static::$_quotas_[$this->descriptor])) {
			for ($i=0; $i<count(static::$_quotas_[$this->descriptor]['_types_']); $i++) {
				if (static::$_quotas_[$this->descriptor]['_types_'][$i]["ID"]==$type_id) {
					$r=static::$_quotas_[$this->descriptor]['_types_'][$i];
					break;
				}
			}
		}
		return $r;
	}
	
	//R�cup�ration de la structure type de quota par son id
	public function get_quota_type_by_name($type_id) {
		$r=array();
		if (isset(static::$_quotas_[$this->descriptor])) {
			for ($i=0; $i<count(static::$_quotas_[$this->descriptor]['_types_']); $i++) {
				if (static::$_quotas_[$this->descriptor]['_types_'][$i]["NAME"]==$type_id) {
					$r=static::$_quotas_[$this->descriptor]['_types_'][$i];
					break;
				}
			}
		}
		return $r;
	}
	
	//R�cup�ration des valeurs li�es aux param�tres g�n�raux du type de quota
	public function get_values() {
		global $min_value,$max_value,$default_value,$conflict_value,$conflict_list,$force_lend,$max_quota;
		
		$requete="select constraint_type, elements, value from ".$this->table." where quota_type=".$this->quota_type["ID"]." and constraint_type in ('MIN','MAX','DEFAULT','CONFLICT','PRIORITY','FORCE_LEND','MAX_QUOTA')";
		$resultat=pmb_mysql_query($requete);
		while ($r=pmb_mysql_fetch_object($resultat)) {
			switch ($r->constraint_type) {
				case 'MIN':
					if ($this->quota_type["MIN"])
						$min_value=$r->value;
					else
						$min_value=0;
					break;
				case 'MAX':
					if ($this->quota_type["MAX"])
						$max_value=$r->value;
					else
						$max_value=0;
					break;
				case 'DEFAULT':
					$default_value=$r->value;
					break;
				case 'CONFLICT':
					$conflict_value=$r->value;
					break;
				case 'PRIORITY':
					$conflict_list[$r->value]=$r->elements;
					break;
				case 'FORCE_LEND':
					$force_lend=1;
					break;
				case 'MAX_QUOTA':
					$max_quota=$r->value;
					break;
			}
		} 
		
		if (!$conflict_value) $conflict_value=4;
		//Valeurs par d�faut de v�rification
		$n=0;
		if ($conflict_value==4) {
			for ($i=(count($this->quota_type["QUOTAS"])-1); $i>=0; $i--) {
				if (!isset($conflict_list[$n]) || !$conflict_list[$n]) {
					$conflict_list[$n]=$this->get_elements_id_by_names($this->quota_type["QUOTAS"][$i]);
				}
				$n++;
			}
		}
	}
	
	//R�cup�ration du tableau des ids de chaque �l�ment composant un id multiple
	public function get_table_ids_from_elements_id($id) {
		$r=array();
		for ($i=0; $i<count(static::$_quotas_[$this->descriptor]['_elements_']); $i++) {
			if (((int)$id&(int)static::$_quotas_[$this->descriptor]['_elements_'][$i]["ID"])==(int)static::$_quotas_[$this->descriptor]['_elements_'][$i]["ID"]) {
				$r[]=static::$_quotas_[$this->descriptor]['_elements_'][$i]["ID"];
			}
		}
		return $r;
	}
	
	//R�cup�ration du tableau des ids de chaque �l�ment composant un id multiple tri� par ordre d�crit dans le type de quota
	public function get_table_ids_from_elements_id_ordered($id) {
		$ids=array();
		for ($i=0; $i<count($this->quota_type["QUOTAS"]); $i++) {
			if ($this->get_elements_id_by_names($this->quota_type["QUOTAS"][$i])==$id) {
				$names=explode(",",$this->quota_type["QUOTAS"][$i]);
				for ($j=0; $j<count($names); $j++) {
					$ids[]=$this->get_element_id_by_name($names[$j]);
				}
				break;
			}
		}
		return $ids;
	}
	
	public function get_quotas_index_from_elements_id($id) {
		for ($i=0; $i<count($this->quota_type["QUOTAS"]); $i++) {
			if ($this->get_elements_id_by_names($this->quota_type["QUOTAS"][$i])==$id) {
				return $i;
			}
		}
		return -1;
	}
	
	//R�cup�ration du titre correspondant aux �l�ments du quota (par xxx et par xxx et ...)
	public function get_title_by_elements_id($id) {
		global $msg;
		
		$ids=$this->get_table_ids_from_elements_id($id);
		$r=array();
		for ($i=0; $i<count($ids); $i++) {
			$r[]=$msg["quotas_by"]." ".static::$_quotas_[$this->descriptor]['_elements_'][$this->get_element_by_id($ids[$i])]["COMMENT"];
		}
		return implode(" ".$msg["quotas_and"]." ",$r);
	}
	
	//R�cup�ration des valeurs de quota et de for�age dans la base pour un ou des �l�ments
	public function get_elements_values($id) {
		
		//R�cup�ration des valeurs de quota
		$requete="select quota_type,constraint_type,elements,value from ".$this->table." where quota_type=".$this->quota_type["ID"]." and elements=".$id." and (constraint_type like 'Q:%' or constraint_type like 'F:%')";
		$resultat=pmb_mysql_query($requete);
		while ($r=pmb_mysql_fetch_object($resultat)) {
			//Analyse de la contrainte
			$constraint=substr($r->constraint_type,2);
			$constraint_type=substr($r->constraint_type,0,1);
			$values=explode(",",$constraint);
			$ids=array();
			for ($i=0; $i<count($values); $i++) {
				$v=explode("=",$values[$i]);
				$ids[$v[0]]=$v[1];
			}
			if ($constraint_type=="Q") {
				$this->elements_values_id[]=$ids;
				$this->elements_values[]=$r->value;
			} else {
				$this->elements_forc_id[]=$ids;
				$this->elements_forc[]=$r->value;
			}
		}
	}
	
	//Recherche d'une valeur de quota d�j� saisie avec les valeurs de chaque �l�ment
	public function search_for_element_value($values_ids,$ids) {
		
		if(is_array($this->elements_values_id) && count($this->elements_values_id)){
			for ($i=0; $i<count($this->elements_values_id); $i++) {
				$elvid=$this->elements_values_id[$i];
				$test=1;
				for ($j=0; $j<count($ids); $j++) {
					$test&=($elvid[$ids[$j]]==$values_ids[$j]);
				}
				if ($test) return $this->elements_values[$i];
			}
		}
		return "";
	}
	
	//Recherche d'une valeur de for�age d�j� saisie avec les valeurs de chaque �l�ment
	public function search_for_element_forc($values_ids,$ids) {
		
		if(is_array($this->elements_forc_id) && count($this->elements_forc_id)){
			for ($i=0; $i<count($this->elements_forc_id); $i++) {
				$elvid=$this->elements_forc_id[$i];
				$test=1;
				for ($j=0; $j<count($ids); $j++) {
					$test&=($elvid[$ids[$j]]==$values_ids[$j]);
				}
				if ($test) return $this->elements_forc[$i];
			}
		}
		return "";
	}
	
	//Affichage r�cursif de la liste des quotas pour les �l�ments choisis
	public function show_quota_list($level,$prefixe_label,$prefixe_varname,$ids,$elements,&$ta) {
		global $min_value,$max_value,$max_quota;
		global $msg;
		global $charset;
		global $class_path;
		
		$jsscript="r=true; if (!check_nan(this)) r=false; ";
		if (($this->quota_type["MAX"])&&($max_value)&&(!$max_quota)) {
			$jsscript.="if (!check_max('!!val!!',$max_value)) r=false; ";
		}
		if (($this->quota_type["MIN"])&&($min_value)&&(!$max_quota)) {
			$jsscript.="if (!check_min('!!val!!',$min_value)) r=false; ";
		}
		if ($jsscript) $jsscript.=" if (!r) { this.value=''; this.focus(); return false;}";
		//Peut-on forcer le pr�t ?	
		if ($this->quota_type["FORCELEND"]) $force_lend=1;
		//Pour chaque �l�ment
		for ($i=0; $i<count($elements[$ids[$level]]); $i++) {
			
			//On r�cup�re le label du champ concern�
			$prefixe_label_=$elements[$ids[$level]][$i]["LABEL"];
			
			//Construction du nom de la variable
			$prefixe_varname_=$prefixe_varname."_".$elements[$ids[$level]][$i]["ID"];
			$ta.="<tr>";
			for ($j=0; $j<$level; $j++) {
					$ta.="<td>&nbsp;</td>";
			}
			//Si on n'est pas au dernier niveau, on appel r�cursivement
			if ($level<(count($ids)-1)) {
				$ta.="<th colspan='".(count($ids)+1+$force_lend-$level)."'>".$prefixe_label_."</th></tr>\n"; 
				//Appel r�cursif
				$this->show_quota_list($level+1,$prefixe_label_,$prefixe_varname_,$ids,$elements,$ta);
			} else {
				//Si c'est le dernier niveau
				$values_ids=explode("_",substr($prefixe_varname_,1));
				//Recherche d'une valeur de quota d�j� enregistr�e
				$quota=$this->search_for_element_value($values_ids,$ids);
				//Recherche d'une valeur de for�age de pr�t d�j� enregistr�e
				if ($force_lend) {
					$forc=$this->search_for_element_forc($values_ids,$ids);
					if ($forc) $checked="checked"; else $checked="";
				}
				if ($jsscript) $jsscript_=str_replace("!!val!!","val".$prefixe_varname_,$jsscript); else $jsscript_="";
				$ta.="<td>".$prefixe_label_."</td><td>";
				if($this->quota_type['SPECIALCLASS']){
					require_once($class_path."/".$this->quota_type['SPECIALCLASS'].".class.php");
					$ta.= call_user_func(array($this->quota_type['SPECIALCLASS'],'get_quota_form'),"val".$prefixe_varname_,$quota);
				}else{
					$ta.="<input type='text' class='saisie-5em' name='val".$prefixe_varname_."' value='".$quota."' ";
					if ($jsscript_) $ta.="onChange=\"".$jsscript_."\"/>";
				}
				$ta.="</td>";
				if ($force_lend)
					$ta.="<td><input type='checkbox' name='forc".$prefixe_varname_."' value='1' ".$checked."/></td>";
				$ta.="</tr>\n";
			}
		}
	}
	
	//Affichage de la table des quotas
	public function show_quota_table($elements_id) {
		global $force_lend;
		global $msg;
		global $charset;
		
		$this->get_values();
		$this->get_elements_values($elements_id);
	
		$ids=$this->get_table_ids_from_elements_id_ordered($elements_id);
		
		//Pour chaque id on r�cup�re les identifiants et les labels du champ concern�
		//Stockage dans $elements
		for ($i=0; $i<count($ids); $i++) {
			$_quota_element_=static::$_quotas_[$this->descriptor]['_elements_'][$this->get_element_by_id($ids[$i])];
			
			$requete="select ".$_quota_element_["FIELD"].", ".$_quota_element_["LABEL"]." from ".$_quota_element_["TABLE"]." order by ".$_quota_element_["LABEL"];
			$resultat=pmb_mysql_query($requete);
			while ($r=pmb_mysql_fetch_array($resultat)) {
				$t=array();
				$t["ID"]=$r[$_quota_element_["FIELD"]];
				$t["LABEL"]=$r[$_quota_element_["LABEL"]];
				$elements[$ids[$i]][]=$t;
			}
		}
		//Affichage
		//Fonctions de v�rification des bornes
		if ($this->quota_type["MIN"]) {
			$ta.="<script>
				function check_min(variable,min_value) {
					if (eval('document.forms[0].'+variable+'.value')<min_value) {
						alert('".addslashes($msg["quotas_lt_min"])." '+min_value);
						return false;
					}
					return true;
				}
			</script>\n";
		}
		if ($this->quota_type["MAX"]) {
			$ta.="<script>
				function check_max(variable,max_value) {
					if (eval('document.forms[0].'+variable+'.value')>max_value) {
						alert('".addslashes($msg["quotas_gt_max"])." '+max_value);
						return false;
					}
					return true;
				}
			</script>\n";
		}
		$ta.="<script>
			function check_nan(e) {
				if(isNaN(e.value)) { 
					alert('".addslashes($msg["quotas_nan"])."');
					return false;
				}
				return true;
			}
		</script>\n";
		$ta.="<table>\n";
		$ta.="<tr>";
		for ($i=0; $i<count($ids); $i++) {
			$ta.="<th>".static::$_quotas_[$this->descriptor]['_elements_'][$this->get_element_by_id($ids[$i])]["COMMENT"]."</th>";
		}
		
		$force="";
		if ($this->quota_type["FORCELEND"]) {
			if ($force_lend)
				//Si le for�age est autoris� en r�gle g�n�rale, en particulier il faut proposer l'interdiction 
				$force=$msg["quotas_dont_lend_element"];
			else
				//Si le for�age n'est pas autoris� en r�gle g�n�rale, il faut proposer l'autorisation de le faire
				$force=$msg["quotas_force_lend_element"];
		}	
		
		
		$ta.="<th>".$this->quota_type["SHORT_COMMENT"]."</th>";
		if ($force) $ta.="<th>".htmlentities($force,ENT_QUOTES,$charset)."</th>";
		$ta.="</tr>\n";
		//Affichage r�cursif de la liste des quotas
		$this->show_quota_list(0,"","",$ids,$elements,$ta);
		$ta.="</table>\n";
		return $ta;
	}
	
	//Enregistrement r�cursif des quotas saisis
	public function rec_quota_list($level,$prefixe_varname,$ids,$elements,$elements_id) {
		//For�age du pr�t ?
		if ($this->quota_type["FORCELEND"]) $force_lend=1;
		//Pour tous les �l�ments
		for ($i=0; $i<count($elements[$ids[$level]]); $i++) {
			$prefixe_varname_=$prefixe_varname."_".$elements[$ids[$level]][$i]["ID"];
			//Si on n'est pas au dernier niveau alors appel r�cursif
			if ($level<(count($ids)-1)) {
				$this->rec_quota_list($level+1,$prefixe_varname_,$ids,$elements,$elements_id);
			} else {
				//Si l'on est au dernier niveau
				$values_ids=explode("_",substr($prefixe_varname_,1));
				$val="val".$prefixe_varname_;
				if ($force_lend) {
					$forc="forc".$prefixe_varname_;
					global ${$forc};
				}
				global ${$val};
				if($this->quota_type['SPECIALCLASS']){
					global $class_path;
					require_once($class_path."/".$this->quota_type['SPECIALCLASS'].".class.php");
					$value_to_store = call_user_func(array($this->quota_type['SPECIALCLASS'],'get_storable_value'),${$val});
				}else{
					$value_to_store = ${$val};
				}
				//Si il y a une valeur de quota saisie, construction de la requ�te d'enregistrement
				if ($value_to_store!=="") {
					$constraint="Q:";
					$quota=array();
					for ($j=0; $j<count($ids); $j++) {
						$quota[]=$ids[$j]."=".$values_ids[$j];
					}
					$constraint.=implode(",",$quota);
					$requete="insert into ".$this->table." (quota_type,constraint_type,elements,value) values(".$this->quota_type["ID"].",'".$constraint."',$elements_id,'".$value_to_store."')";
					pmb_mysql_query($requete);
				}
				//Si le for�age est coch�, construction de la requ�te d'enregistrement
				if ((${$forc})&&($force_lend)) {
					$constraint="F:";
					$quota=array();
					for ($j=0; $j<count($ids); $j++) {
						$quota[]=$ids[$j]."=".$values_ids[$j];
					}
					$constraint.=implode(",",$quota);
					$requete="insert into ".$this->table." (quota_type,constraint_type,elements,value) values(".$this->quota_type["ID"].",'".$constraint."',$elements_id,".${$forc}.")";
					pmb_mysql_query($requete);
				}
			}
		}
	}
	
	//Enregistrement des quotas apr�s soumission du tableau
	public function rec_quota($elements_id) {
		global $first;
		global $ids_order;
		//Si formulaire soumis
		if ($first) {
			//Suppression des quotas dans la table
			$requete="delete from ".$this->table." where quota_type=".$this->quota_type["ID"]." and (constraint_type like 'Q:%' or constraint_type like 'F:%') and elements=".$elements_id;
			pmb_mysql_query($requete);
			
			//Recherche des combinaisons possibles et enregistrement
			//Liste des ids
			$ids=explode(",",$ids_order);
			
			//Pour chaque id on r�cup�re l'identifiant et le label
			for ($i=0; $i<count($ids); $i++) {
				$_quota_element_=static::$_quotas_[$this->descriptor]['_elements_'][$this->get_element_by_id($ids[$i])];
				$requete="select ".$_quota_element_["FIELD"].", ".$_quota_element_["LABEL"]." from ".$_quota_element_["TABLE"];
				$resultat=pmb_mysql_query($requete);
				while ($r=pmb_mysql_fetch_array($resultat)) {
					$t=array();
					$t["ID"]=$r[$_quota_element_["FIELD"]];
					$t["LABEL"]=$r[$_quota_element_["LABEL"]];
					$elements[$ids[$i]][]=$t;
				}
			}
			
			//Enregistrement r�cursif
			$this->rec_quota_list(0,"",$ids,$elements,$elements_id);
		}
	}
	
	public function apply_conflict($q) {
		global $conflict_value, $default_value, $conflict_list;
		if(!$q)return;
		$this->get_values();
		$test=true;
		$nb_quotas=0;
		$total_quotas=0;
		$total_id_quotas=0;
		$max_quota="";
		$max_quota_id=0;
		$min_quota="";
		$min_quota_id=0;
		$r=array();
		$r_error=array("ID"=>"","VALUE"=>-1);
		reset($q);
		//Test valeurs toutes vides et calcul du min, du max et du total
		while (list($key,$val)=each($q)) {
			$test&=($val==="");
			if ($val!=="") {
				if ($max_quota==="") {
					$max_quota=$val;
					$max_quota_id=$key;
				} else
					if ($val>$max_quota) {
						$max_quota=$val;
						$max_quota_id=$key;
					}
				if ($min_quota==="") {
					$min_quota=$val;
					$min_quota_id=$key;
				} else
					if ($val<$min_quota) {
						$min_quota=$val;
						$min_quota_id=$key;
					}
				$nb_quotas++;
				$total_quotas=intval($val);
				$total_id_quotas=intval($key);
			}
		}
		//Si les valeurs sont toutes vides et qu'il y a une valeur par d�faut alors on la renvoie
		if (($test)&&($default_value)) {
			$r["ID"]=""; 
			$r["VALUE"]=$default_value;
			return $r;
		}
		//Si les valeurs sont toutes vides et qu'il n'y a pas de valeur par d�faut alors erreur
		if (($test)&&(!$default_value)) return $r_error;
		//Si il n'y a qu'un quota possible, on le renvoie
		if ($nb_quotas==1) {
			$r["ID"]=$total_id_quotas;
			if($this->quota_type['SPECIALCLASS']){
				$r["VALUE"]=$q[$r["ID"]];
			}else{
				$r["VALUE"]=$total_quotas;
			}
			return $r;
		}
		//Sinon, on applique les r�gles de priorit�
		switch ($conflict_value) {
			case 1:
				//Le maximum
				$r["ID"]=$max_quota_id;
				$r["VALUE"]=$max_quota;
				return $r;
				break;
			case 2:
				//Le minimum
				$r["ID"]=$min_quota_id;
				$r["VALUE"]=$min_quota;
				return $r;
				break;
			case 3:
				//La valeur par d�faut
				if ($default_value) {
					$r["ID"]="";
					$r["VALUE"]=$default_value;
					return $r;
				} else return $r_error;
				break;
			case 4:
				//Dans l'ordre
				for ($i=0; $i<count($conflict_list); $i++) {
					if ($q[$conflict_list[$i]]!=="") {
						$r["ID"]=$conflict_list[$i];
						$r["VALUE"]=$q[$conflict_list[$i]];
						return $r;
					}
				}
				break;
			default:
				//Si plantage g�n�ral, retourne erreur !!
				return $r_error;
		}
	}
	
	//R�cup�ration de la valeur de for�age d'un quota par son id
	public function get_force_value_by_id($struct,$element_id) {
		//v�rification des param�tres
		for ($i=0; $i<count($this->quota_type["QUOTAS"]); $i++) {
			$on=explode(",",$this->quota_type["QUOTAS"][$i]);
			for ($j=0; $j<count($on); $j++) {
				$s[$on[$j]]=$this->get_element_by_name($on[$j]);
			}
		}
		reset($s);
		$flag=true;
		$values=array();
		while (list($key,$val)=each($s)) {
			$_quota_element_=static::$_quotas_[$this->descriptor]['_elements_'][$val];
			if ($struct[$_quota_element_["LINKEDTO"]]=="") {
				$flag=false;
				break;
			} else {
				$struct_=$struct;
				//Si c'est une recherche indirecte (tout �a pour faire plaisir � Eric !!)
				$flag_indirect=false;
				if ($_quota_element_["TABLELINKED_BY"]) {
					$indirect=$this->get_object_for_indirect_element($_quota_element_,$struct_);
					if ($indirect!=-1) {
						$flag_indirect=true;
						$struct_[$_quota_element_["LINKEDID"]]=$indirect;
					} else {
						$flag=false;
						break;
					}
				}
				$requete="select ".$_quota_element_["LINKEDFIELD"]." from ".$_quota_element_["TABLELINKED"];
				$requete.=" where ".$_quota_element_["LINKEDID"]."='".$struct_[$flag_indirect?$_quota_element_["LINKEDID"]:$_quota_element_["LINKEDTO"]]."'";
				$resultat=pmb_mysql_query($requete);
				$values[$_quota_element_["ID"]]=@pmb_mysql_result($resultat,0,0);
			}
		}
		if ($flag) {
			//Pour le quota, r�cup�ration des valeurs
			$elements_id=$element_id;
			$ids=$this->get_table_ids_from_elements_id($elements_id);
			//Construction de la requ�te
			$from="";
			$where="";
			for ($j=0; $j<count($ids); $j++) {
				if ($from) $from.=",";
				$from.=$this->table." as q$j";
				$fregexp="F:".$ids[$j]."=".$values[$ids[$j]]."$|F:".$ids[$j]."=".$values[$ids[$j]].",|F:.*,".$ids[$j]."=".$values[$ids[$j]]."$|F:.*,".$ids[$j]."=".$values[$ids[$j]].",";
				if ($where) {
					$where.=" and q$j.constraint_type regexp '".$fregexp."' and q$j.quota_type=q0.quota_type and q$j.constraint_type=q0.constraint_type and q$j.elements=q0.elements";
				} else {
					$where="q0.constraint_type regexp '".$fregexp."' and q0.quota_type=".$this->quota_type["ID"]." and q0.elements=".$elements_id;
				}
			}
			$requete="select q0.value from ".$from." where ".$where;
			$resultat=pmb_mysql_query($requete);
			if (pmb_mysql_num_rows($resultat))
				return pmb_mysql_result($resultat,0,0);
			else
				return 0;
		} else return 0;
	}
	
	//R�cup�ration de la valeur d'un quota par son id
	public function get_quota_value_by_id($struct,$element_id) {
		//v�rification des param�tres
		for ($i=0; $i<count($this->quota_type["QUOTAS"]); $i++) {
			$on=explode(",",$this->quota_type["QUOTAS"][$i]);
			for ($j=0; $j<count($on); $j++) {
				$s[$on[$j]]=$this->get_element_by_name($on[$j]);
			}
		}
		reset($s);
		$flag=true;
		$values=array();
		while (list($key,$val)=each($s)) {
			$_quota_element_=static::$_quotas_[$this->descriptor]['_elements_'][$val];
			if ($struct[$_quota_element_["LINKEDTO"]]=="") {
				$flag=false;
				break;
			} else {
				$requete="select ".$_quota_element_["LINKEDFIELD"]." from ".$_quota_element_["TABLELINKED"];
				$requete.=" where ".$_quota_element_["LINKEDID"]."='".$struct[$_quota_element_["LINKEDTO"]]."'";
				$resultat=pmb_mysql_query($requete);
				$values[$_quota_element_["ID"]]=@pmb_mysql_result($resultat,0,0);
			}
		}
		if ($flag) {
			//Pour le quota, r�cup�ration des valeurs
			$elements_id=$element_id;
			$ids=$this->get_table_ids_from_elements_id($elements_id);
			//Construction de la requ�te
			$from="";
			$where="";
			for ($j=0; $j<count($ids); $j++) {
				if ($from) $from.=",";
				$from.=$this->table." as q$j";
				$qregexp="Q:".$ids[$j]."=".$values[$ids[$j]]."$|Q:".$ids[$j]."=".$values[$ids[$j]].",|Q:.*,".$ids[$j]."=".$values[$ids[$j]]."$|Q:.*,".$ids[$j]."=".$values[$ids[$j]].",";
				if ($where) {
					$where.=" and q$j.constraint_type regexp '".$qregexp."' and q$j.quota_type=q0.quota_type and q$j.constraint_type=q0.constraint_type and q$j.elements=q0.elements";
				} else {
					$where="q0.constraint_type regexp '".$qregexp."' and q0.quota_type=".$this->quota_type["ID"]." and q0.elements=".$elements_id;
				}
			}
			$requete="select q0.value from ".$from." where ".$where;
			$resultat=pmb_mysql_query($requete);
			if (pmb_mysql_num_rows($resultat))
				return pmb_mysql_result($resultat,0,0);
			else
				return -1;
		} else return -1;
	}
	
	public function get_object_for_indirect_element($_quota_element_,$struct) {
		$flag=true;
		//Je prends tous les �l�ments que je trouve et je recherche r�cursivement le plus d�favorale
		$requete="select distinct ".$_quota_element_["LINKEDFIELD"]." from ".$_quota_element_["TABLELINKED"];
		$requete.=" where ".$_quota_element_["LINKEDID_BY"]."='".$struct[$_quota_element_["LINKEDTO"]]."'";
		$resultat=@pmb_mysql_query($requete);
		if (!pmb_mysql_num_rows($resultat)) {
			$flag=false;
		} else {
			$min_quota=-1;
			$min_quota_with_id=array();
			$no_limit=0;
			$no_limit_with_id=array();
			while ($r=pmb_mysql_fetch_array($resultat)) {
				$struct_=$struct;
				//Pour l'�l�ment, je recherche le premier objet associ�
				$requete="select ".$_quota_element_["LINKEDID"]." from ".$_quota_element_["TABLELINKED"]." where ".$_quota_element_["LINKEDID_BY"]."='".$struct[$_quota_element_["LINKEDTO"]]."' and ".$_quota_element_["LINKEDFIELD"]."='".$r[$_quota_element_["LINKEDFIELD"]]."' limit 1";
				$resultat_object=@pmb_mysql_query($requete);
				$struct_[$_quota_element_["LINKEDTO"]]=pmb_mysql_result($resultat_object,0,0);
				$quota_by=$this->get_quota_value_with_id($struct_,true);
				if ($quota_by["VALUE"]==-1) {
					$no_limit++;
					$no_limit_with_id=$quota_by;
					$no_limit_with_id["BY"]=$struct_[$_quota_element_["LINKEDTO"]];
				} else {
					if ($min_quota==-1) {
						$min_quota_with_id=$quota_by;
						$min_quota_with_id["BY"]=$struct_[$_quota_element_["LINKEDTO"]];
						$min_quota=$quota_by["VALUE"];
					} else {
						if ($quota_by["VALUE"]<$min_quota) {
							$min_quota_with_id=$quota_by;
							$min_quota=$quota_by["VALUE"];
							$min_quota_with_id["BY"]=$struct_[$_quota_element_["LINKEDTO"]];
						}
					}
				}
			}
			if ($min_quota==-1) $min_quota_with_id=$no_limit_with_id;
		}
		if (!$flag) return -1; else return $min_quota_with_id["BY"];
	}
	
	//R�cup�ration de valeur d'un quota et de son id
	public function get_quota_value_with_id($struct,$by=false) {
		$s=array() ;
		
		//v�rification des param�tres
		for ($i=0; $i<count($this->quota_type["QUOTAS"]); $i++) {
			$on=explode(",",$this->quota_type["QUOTAS"][$i]);
			for ($j=0; $j<count($on); $j++) {
				$s[$on[$j]]=$this->get_element_by_name($on[$j]);
			}
		}
		reset($s);
		$flag=true;
		$values=array();
		while (list($key,$val)=each($s)) {
			$struct_=$struct;
			$_quota_element_=static::$_quotas_[$this->descriptor]['_elements_'][$val];
			if ($struct_[$_quota_element_["LINKEDTO"]]=="") {
				$flag=false;
				break;
			} else {
				//Si c'est une recherche indirecte (tout �a pour faire plaisir � Eric !!)
				$flag_indirect=false;
				if (($_quota_element_["TABLELINKED_BY"])&&(!$by)) {
					$indirect=$this->get_object_for_indirect_element($_quota_element_,$struct_);
					if ($indirect!=-1) {
						$flag_indirect=true;
						$struct_[$_quota_element_["LINKEDID"]]=$indirect;
					} else {
						$flag=false;
						break;
					}
				}
				//Avec l'objet, je prends l'�l�ment associ�
				$requete="select ".$_quota_element_["LINKEDFIELD"]." from ".$_quota_element_["TABLELINKED"];
				$requete.=" where ".$_quota_element_["LINKEDID"]."='".$struct_[$flag_indirect?$_quota_element_["LINKEDID"]:$_quota_element_["LINKEDTO"]]."'";
				$resultat=pmb_mysql_query($requete);
				$values[$_quota_element_["ID"]]=@pmb_mysql_result($resultat,0,0);
			}
		}
		if ($flag) {
			//Pour chaque quota, r�cup�ration des valeurs
			for ($i=0; $i<count($this->quota_type["QUOTAS"]); $i++) {
				$elements_id=$this->get_elements_id_by_names($this->quota_type["QUOTAS"][$i]);
				$ids=$this->get_table_ids_from_elements_id($elements_id);
				//Construction de la requ�te
				$from="";
				$where="";
				for ($j=0; $j<count($ids); $j++) {
					if ($from) $from.=",";
					$from.=$this->table." as q$j";
					$qregexp="Q:".$ids[$j]."=".$values[$ids[$j]]."$|Q:".$ids[$j]."=".$values[$ids[$j]].",|Q:.*,".$ids[$j]."=".$values[$ids[$j]]."$|Q:.*,".$ids[$j]."=".$values[$ids[$j]].",";
					if ($where) {
						$where.=" and q$j.constraint_type regexp '".$qregexp."' and q$j.quota_type=q0.quota_type and q$j.constraint_type=q0.constraint_type and q$j.elements=q0.elements";
					} else {
						$where="q0.constraint_type regexp '".$qregexp."' and q0.quota_type=".$this->quota_type["ID"]." and q0.elements=".$elements_id;
					}
				}
				$requete="select q0.value from ".$from." where ".$where;
				$resultat=pmb_mysql_query($requete);
				if (pmb_mysql_num_rows($resultat))
					$q[$elements_id]=pmb_mysql_result($resultat,0,0);
				else
					$q[$elements_id]="";
			}
			//Application des r�gles de priorit�
			return $this->apply_conflict($q);
		} else return array("ID"=>"","VALUE"=>-1);
	}
	
	public function get_quota_value($struct) {
		$r=$this->get_quota_value_with_id($struct);
		return $r["VALUE"];
	}
	
	public function get_force_value($struct) {
		global $force_lend;
		$r=$this->get_quota_value_with_id($struct);
		$force=$force_lend;
		
	
		if (($this->quota_type["FORCELEND"])&&($r["ID"]))
		{
			$force^=$this->get_force_value_by_id($struct,$r["ID"]);
		}
		
		return $force;
			
	}
	
	public function get_criterias_title_by_elements_id($struct,$id) {
		global $msg;
		
		$title=array();
		
		$ids=$this->get_table_ids_from_elements_id_ordered($id);
		$where = '';
		for ($i=0; $i<count($ids); $i++) {
			$element=static::$_quotas_[$this->descriptor]['_elements_'][$this->get_element_by_id($ids[$i])];
			if ($element["TABLE"]==$element["TABLELINKED"]) {
				$requete="select ".$element["LABEL"]." from ".$element["TABLE"]." where ".$element["FIELD"]."='".$struct[$element["LINKEDTO"]]."'";
			} else {
				$struct_=$struct;
				//Si c'est un object indirect (ex : notice pass�e pour le type d'exemplaire) alors on va chercher l'objet ayant le quota le plus d�favorable
				if ($element["TABLELINKED_BY"]) {
					$indirect=$this->get_object_for_indirect_element($element,$struct_);
					if ($indirect!=-1) {
						$struct_[$element["LINKEDTO"]]=$indirect;
						//R�cup�ration de l'id de l'�l�ment � partir de ce qui est transmis dans struct
						$requete="select ".$element["LINKEDFIELD"]." from ".$element["TABLELINKED"]." where ".$element["LINKEDID"]."='".$struct_[$element["LINKEDTO"]]."'";
						$resultat=pmb_mysql_query($requete);
						$linkedid=@pmb_mysql_result($resultat,0,0);
						$where =" and ".$element["LINKEDFIELD"]."='".$linkedid."'";
					} else {
						$struct_[$element["LINKEDTO"]]=0;
						$where ="";
					}
				}
				$requete="select ".$element["LABEL"]." from ".$element["TABLE"].", ".$element["TABLELINKED"]." where ".$element["FIELD"]."=".$element["LINKEDFIELD"]." and ".$element["LINKEDID"]."='".$struct_[$element["LINKEDTO"]]."'".$where;
			}
			$resultat=pmb_mysql_query($requete);
			$title[]=@pmb_mysql_result($resultat,0,0);
		}
		return implode(" ".$msg["quotas_and"]." ",$title);
	}
	
	public function check_quota($struct) {
		global $max_value,$max_quota,$force_lend;
		global $msg;
		
		//R�cup�ration du quota � partir des param�tres pass�s
		$r=$this->get_quota_value_with_id($struct);
	
		//R�cup�ration de l'entit�
		$_quota_element_entity_=static::$_quotas_[$this->descriptor]['_elements_'][$this->get_element_by_name($this->quota_type["ENTITY"])];
			
		//Le for�age est initialis� � la valeur des param�tres g�n�raux
		$force=$force_lend;
		
		//Au d�part, il n'y a pas d'erreur
		$error=0;
		
		//Compte du nombre total d�j� pr�sent
		$requete="select count(1) from ".$this->quota_type["COUNT_TABLE"]." where ".$this->quota_type["COUNT_FIELD"]."=".$struct[$_quota_element_entity_["LINKEDTO"]];
		if ($this->quota_type["COUNT_FILTER"]) $requete.=" and ".$this->quota_type["COUNT_FILTER"];

		$resultat=pmb_mysql_query($requete);
		$nb_total=@pmb_mysql_result($resultat,0,0);

		//!!A v�rifier si valeur par d�faut equiv quota le plus large !

		//Machine d'�tat
		$state="QUOTA_START";
		
		while ($state!="QUOTA_END") {
			switch ($state) {
				case "QUOTA_START":
					//V�rification que l'on a r�cup�r� une contrainte : si oui -> v�rification du nombre li� � cette contrainte (QUOTA_CHECK)
					//Si non, on v�rifie juste que le nombre total ne d�passera pas le maximum possible (QUOTA_MAX)
					if ($r["VALUE"]==-1) $state="QUOTA_MAX"; else $state="QUOTA_CHECK";
					break;
				case "QUOTA_CHECK":
					//Est-ce que le quota est l'entit� ou est ind�termin� ?
					//Si entit� ou vide --> On v�rifie que le total comptabilis� ne d�passe pas ce quota (QUOTA_ENTITY_OR_EMPTY)
					//Si pas entit� et pas vide --> On v�rifie que le total partiel ne d�passe pas le quota (QUOTA_PARTIAL)
					if (($r["ID"]==$_quota_element_entity_["ID"])||($r["ID"]=="")) $state="QUOTA_ENTITY_OR_EMPTY"; else $state="QUOTA_PARTIAL";
					break;
				case "QUOTA_PARTIAL":
					//V�rification que le nombre total partiel ne d�passe pas le quota
					//Si nombre partiel d�passe --> erreur et fin (QUOTA_END)
					//Si nombre partiel ne d�passe pas --> V�rification que le nombre total ne d�passera pas le maximum autoris� (QUOTA_MAX)
					
					//calcul du nombre partiel
					$quota=$this->get_quotas_index_from_elements_id($r["ID"]);
					$elements=$this->quota_type["QUOTAS"][$quota];
					$countfields=$this->quota_type["COUNTFIELDS"][$quota];
					
					$elements_=explode(",",$elements);
					$countfields_=explode(",",$countfields);
					$from="";
					$where="";
					
					//Pour chaque �l�ment
					//$groupby_=array();
					$tablelinked=array();
					$count_distinct=array();
					for ($i=0; $i<count($elements_); $i++) {
						$struct_=$struct;
						//Si ce n'est pas l'entit�
						if ($elements_[$i]<>$this->quota_type["ENTITY"]) {
							//R�qup�ration de l'�l�ment par son nom
							$element=static::$_quotas_[$this->descriptor]['_elements_'][$this->get_element_by_name($elements_[$i])];
							//Lien avec la table de l'objet (ex : exemplaire)
							//La table a-t-elle d�j� �t� cit�e dans la requete ?
							if (!isset($tablelinked[$element["TABLELINKED"]]) || !$tablelinked[$element["TABLELINKED"]]) {
								$from.=", ".$element["TABLELINKED"];
								$tablelinked[$element["TABLELINKED"]]=true;
								$already_present=false;
							} else $already_present=true;
							//Si c'est un object indirect (ex : notice pass�e pour le type d'exemplaire) alors on va chercher l'objet ayant le quota le plus d�favorable
							if ($element["TABLELINKED_BY"]) {
								$indirect=$this->get_object_for_indirect_element($element,$struct_);
								if ($indirect!=-1)
									$struct_[$element["LINKEDTO"]]=$indirect;
								else
									$struct_[$element["LINKEDTO"]]=0;
							}
							//R�cup�ration de l'id de l'�l�ment � partir de ce qui est transmis dans struct
							$requete="select ".$element["LINKEDFIELD"]." from ".$element["TABLELINKED"]." where ".$element["LINKEDID"]."='".$struct_[$element["LINKEDTO"]]."'";
							$resultat=pmb_mysql_query($requete);
							$linkedid=@pmb_mysql_result($resultat,0,0);
							if ($element["TABLELINKED_BY"]) {
								if (!$already_present) {
									$where.=" and ".$countfields_[$i]."=".$element["LINKEDID_BY"];
									$count_distinct[]=$countfields_[$i];
								}
								$where.=" and ".$element["LINKEDFIELD"]."='".$linkedid."'";
								//$groupby_[]=$countfields_[$i];
							} else {
								if (!$already_present) {
									$where.=" and ".$countfields_[$i]."=".$element["LINKEDID"];
									$count_distinct[]=$countfields_[$i];
								}
								$where.=" and ".$element["LINKEDFIELD"]."='".$linkedid."'";
							}
						}
					}
					//$group_by=implode(",",$groupby_);
					if((count($elements_)==1) && ($elements_[0]!=$this->quota_type["ENTITY"]) && (!$element["TABLELINKED_BY"])){
						$distinct="1";
					}else{
						$distinct=count($count_distinct)?"distinct ".implode(",",$count_distinct):"1";
					}
					$requete="select count(".$distinct.") from ".$this->quota_type["COUNT_TABLE"].$from." where ".$this->quota_type["COUNT_FIELD"]."='".$struct[$_quota_element_entity_["LINKEDTO"]]."'".$where;
					if ($this->quota_type["COUNT_FILTER"]) $requete.=" and ".$this->quota_type["COUNT_FILTER"];
					//if ($group_by) $requete.=" group by ".$group_by;
					$resultat=pmb_mysql_query($requete);
					$nb_partial=@pmb_mysql_result($resultat,0,0);
					//Si nombre partiel+1>quota alors non !!
					if ($nb_partial+1>$r["VALUE"]) {
						$this->error_message=sprintf($this->quota_type["PARTIAL_ERROR_MESSAGE"],$this->get_criterias_title_by_elements_id($struct,$r["ID"]),$r["VALUE"]);
						if ($this->quota_type["FORCELEND"]) {
							$force^=$this->get_force_value_by_id($struct,$r["ID"]);
						}
						$this->force=$force;
						$error=-1;
						$state="QUOTA_END"; 
					} else {
						$error=0;
						$state="QUOTA_MAX";
					}
					break;	
				case "QUOTA_ENTITY_OR_EMPTY":
					//V�rification que le nombre total ne d�passe pas le quota
					//Si d�passement --> erreur et fin (QUOTA_END)
					//Si pas d�passement --> V�rification que le nombre total de d�passera pas le maximum autoris� (QUOTA_MAX)
				
					//Si nombre total+1>quota alors non !!
					if ($nb_total+1>$r["VALUE"]) {
						if ($r["ID"]) {
							$this->error_message=sprintf($this->quota_type["PARTIAL_ERROR_MESSAGE"],$this->get_criterias_title_by_elements_id($struct,$r["ID"]),$r["VALUE"]);
							if ($this->quota_type["FORCELEND"])
								$force^=$this->get_force_value_by_id($struct,$r["ID"]);
						}
						else
							$this->error_message=sprintf($this->quota_type["DEFAULT_ERROR_MESSAGE"],$r["VALUE"]);
						$this->force=$force;
						$error=-1;
						$state="QUOTA_END";
					} else {
						$error=0;
						$state="QUOTA_MAX";
					}
					break; 
				case "QUOTA_MAX":
					//V�rification que le nombre total ne d�passera pas le maximum autoris�
					//Si d�passement : erreur
					//Dans tous les cas, on termine (QUOTA_END)
					//R�cup�ration du max
					if ($this->quota_type["MAX"]) {
						$max_=-1;
						$max_message="";
						$partial=0;
						if ($max_quota) {
							$max_=$this->get_quota_value_by_id($struct,$_quota_element_entity_["ID"]);
						}
						if ($max_==-1)
							$max_=$max_value;
						else {
							$partial=1;
							$max_message=$msg["quotas_by"]." ".$this->get_criterias_title_by_elements_id($struct,$_quota_element_entity_["ID"]);
						}
					}
					//Si maximum trouv� et total+1>max_ alors non !!
					if (($max_!=-1)&&($max_!=0)) {
						if ($nb_total+1>$max_) {
							$this->error_message=sprintf($this->quota_type["MAX_ERROR_MESSAGE"],$max_message,$max_);
							if (($this->quota_type["FORCELEND"])&&($partial)) {
								$force^=$this->get_force_value_by_id($struct,$_quota_element_entity_["ID"]);
							}
							$this->force=$force;
							$error=-1;
						} else $error=0;
					} else $error=0;
					$state="QUOTA_END";
					break;
				default:
					return $error;
					break;
			}
		}
		//QUOTA_END atteint : Fin de la v�rification : on renvoie la valeur $error,le message d'erreur si $error=-1 est stock� dans $this->error_message
		//for�age ou non dans $this->force
		return $error;
	}
}

?>