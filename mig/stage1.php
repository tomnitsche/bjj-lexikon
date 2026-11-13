$out='';
// 1) Backups of live pages being replaced
$live=[86,1186,4036,692,852,377,1396,438,1110,1112,1113];
$bk=get_option('gg_relaunch_backups',[]);
foreach($live as $id){ if(isset($bk[$id])) continue; $p=get_post($id); if(!$p) continue;
  $nid=wp_insert_post(['post_type'=>'page','post_status'=>'draft','post_title'=>'BACKUP 13.11. – '.$p->post_title,'post_name'=>'backup-1311-'.$p->post_name,'post_content'=>wp_slash($p->post_content)]);
  $bk[$id]=$nid; }
update_option('gg_relaunch_backups',$bk,false);
$out.='backups '.json_encode($bk)."\n";
// 2) Media
$s=gg_stg();
$pages=[86,1186,4036,692,4695,4692,4762,4763,4764,852,377,1396,438,1110,1112,1113,4744];
$txt='';
foreach($pages as $id){ $txt.=$s->get_var("SELECT post_content FROM wp_posts WHERE ID=$id");
  foreach($s->get_results("SELECT meta_value FROM wp_postmeta WHERE post_id=$id AND meta_key NOT LIKE '\_edit%'") as $m) $txt.=' '.$m->meta_value;
  $th=$s->get_var("SELECT meta_value FROM wp_postmeta WHERE post_id=$id AND meta_key='_thumbnail_id'"); if($th) $txt.=' "id":'.$th; }
$snips=[25,26,27,37,40,41,42,43,44,45,49,50,52,53,54];
foreach($snips as $sid) $txt.=$s->get_var("SELECT code FROM wp_snippets WHERE id=$sid");
$ok=[];$bad=[];
foreach(gg_ids_in($txt) as $aid){ $isatt=$s->get_var("SELECT post_type FROM wp_posts WHERE ID=$aid"); if($isatt!=='attachment') continue; $l=gg_media($aid); if($l) $ok[]="$aid>$l"; else $bad[]=$aid; }
// also attachments referenced by URL only
if(preg_match_all('#staging2\.greyground\.de/wp-content/uploads/(\d{4}/\d{2}/[^\s"\'\)\\\\?]+)#',$txt,$m)){
  foreach(array_unique($m[1]) as $rel){ $base=preg_replace('/-\d+x\d+(\.\w+)$/','$1',$rel);
    $aid=$s->get_var($s->prepare("SELECT post_id FROM wp_postmeta WHERE meta_key='_wp_attached_file' AND (meta_value=%s OR meta_value=%s) LIMIT 1",$rel,$base));
    if($aid){ $l=gg_media($aid); if($l) $ok[]="u$aid>$l"; else $bad[]="u$aid"; } } }
$out.='files '.gg_files($txt)."\n";
$out.='media '.count(array_unique($ok)).' bad '.implode(',',$bad)."\n";
echo $out;
