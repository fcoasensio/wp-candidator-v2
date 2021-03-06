<?php
/*
  Plugin Name: Candideit.org V2
  Plugin URI: https://github.com/ciudadanointeligente/candideit.org-v2-wp
  Description: Displays information for candidates in elections set to be administered through the platform http://candideit.org/
  Author: Fundación Ciudadano Inteligente - Smart Citizen Foundation
  Version: 2.0
  Author URI: http://www.ciudadanointeligente.org
  License: Copyleft
 */
define('URLBASE','http://candideit.org');
// define('URLBASE','http://127.0.0.1:8000');
define('API_VERSION', '/api/v2/');

function canv2_candideitorg_init() {
  $plugin_dir =  plugin_basename( __FILE__ );
  $loaded = load_plugin_textdomain( 'candideitorg', false, dirname($plugin_dir).'/languages/' );
}
// add_action('plugins_loaded', 'candideitorg_init');

function canv2_activate() 
{

  $_name      = 'candideitorgv2';
  $page_title = 'Candideitorg';
  $page_name  = $_name;
  $page_id    = '0';

  delete_option($_name.'_page_title');
  add_option($_name.'_page_title', $page_title, '', 'yes');

  delete_option($_name.'_page_name');
  add_option($_name.'_page_name', $page_name, '', 'yes');

  delete_option($_name.'_page_id');
  add_option($_name.'_page_id', $page_id, '', 'yes');

  $the_page = get_page_by_title($page_title);
  
  if (!$the_page)
  {
    // Create post object
    $_p = array();
    $_p['post_title']     = $page_title;
    $_p['post_content']   = '';
    $_p['post_status']    = 'publish';
    $_p['post_type']      = 'page';
    $_p['comment_status'] = 'closed';
    $_p['ping_status']    = 'closed';
    $_p['post_category'] = array(1); // the default 'Uncatrgorised'

    // Insert the post into the database
    $page_id = wp_insert_post($_p);
  }
  else
  {
    // the plugin may have been previously active and the page may just be trashed...
    $page_id = $the_page->ID;

    //make sure the page is not trashed...
    $the_page->post_status = 'publish';
    $page_id = wp_update_post($the_page);
  }

  delete_option($_name.'_page_id');
  add_option($_name.'_page_id', $page_id);
  
}

register_activation_hook( __FILE__, 'canv2_activate' );

function canv2_deactivate() 
{
  canv2_deletePage();
  canv2_deleteOptions();
}

register_deactivation_hook( __FILE__, 'canv2_deactivate' );

function canv2_uninstall()
{
  canv2_deletePage(true);
  canv2_deleteOptions();
}

register_uninstall_hook( __FILE__, 'canv2_uninstall' );

function canv2_deletePage($hard = false)
{
  global $wpdb;
  $_name      = 'candideitorgv2';

  $id = get_option($_name.'_page_id');
  if($id && $hard == true)
    wp_delete_post($id, true);
  elseif($id && $hard == false)
    wp_delete_post($id);
}

function canv2_deleteOptions()
{
  $_name      = 'candideitorgv2';

  delete_option($_name.'_page_title');
  delete_option($_name.'_page_name');
  delete_option($_name.'_page_id');
}

function canv2_admin_action() {
  add_management_page( "Candideit.org", "Candideit.org-v2", 1, "candideitorgv2", 'canv2_configuration' );
}
add_action('admin_menu', 'canv2_admin_action' );

function canv2_configuration() {
  if (!current_user_can('manage_options')) {
      wp_die( __('You dont have permission to access here', 'candideitorg') );
  }
  
  $msj = '';
  $msj_class = '';

  if ( isset($_POST['candideit_update']) ) {
      ( isset($_POST['candideitv2_api_key']) ) ? update_option('candideitv2_api_key', filter_var($_POST['candideitv2_api_key'],FILTER_SANITIZE_SPECIAL_CHARS) ) : update_option('candideitv2_api_key', '');
      ( isset($_POST['candideitv2_username']) ) ? update_option('candideitv2_username', filter_var($_POST['candideitv2_username'],FILTER_SANITIZE_SPECIAL_CHARS) ) : update_option('candideitv2_username', '');
      ( isset($_POST['candideitv2_election_id']) ) ? update_option('candideitv2_election_id', filter_var($_POST['candideitv2_election_id'],FILTER_SANITIZE_NUMBER_INT) ) : update_option('candideitv2_election_id', '');

      if ( (isset($_POST['candideitv2_api_key'])) && (isset($_POST['candideitv2_username'])) ) {
          $msj = __( 'Update data successfully :)', 'candideitorg' );
          $msj_class = 'updated';
      }
  }

  include "candideitorg-form-config.php";
}

function canv2_getElections($params = array()) {
  $api_url = URLBASE.API_VERSION.'election/?format=json&username='. $params['username'] .'&api_key='. $params['api_key'];

  $get_status = get_headers($api_url);
  
  if($get_status[0] != 'HTTP/1.1 200 OK' AND $get_status[0] != 'HTTP/1.0 200 OK') {
    $aElecciones = array();
  } else {
    $aElecciones = json_decode(file_get_contents($api_url));
  }
  
  return $aElecciones;
}

function canv2_getCandidates($params = array()) {
  $api_url = URLBASE.API_VERSION.'election/'. $params['election_id'] .'/?format=json&username='. $params['username'] .'&api_key='. $params['api_key'];

  $aElection = file_get_contents($api_url);
  $election = json_decode($aElection);
  
  foreach ($election->candidates as $candidate) {
    $url = URLBASE.$candidate.'?format=json&username='. $params['username'] .'&api_key='. $params['api_key'];
    $json_info = file_get_contents($url);
    $aCandidato = json_decode($json_info);
    $aCandidatos[] = $aCandidato->name;
  }

  return $aCandidatos;
}

function canv2_loscandidatos() {

  global $post;
  $post_slug = $post->post_name;
  $mod = filter_var($_GET['mod'], FILTER_SANITIZE_STRING);
  $cid = filter_var($_GET['cid'], FILTER_SANITIZE_NUMBER_INT);

  if ( $post_slug == 'candideitorg' ) {
    
    switch($mod) {
      case 'ficha':
        $url = URLBASE.API_VERSION.'candidate/'. $cid .'/?format=json&username='. get_option('candideitv2_username') .'&api_key='. get_option('candideitv2_api_key');
        $get_status = get_headers($url);

        if($get_status[0] == 'HTTP/1.1 200 OK' OR $get_status[0] == 'HTTP/1.0 200 OK') {
          
          $aCandidate = json_decode(file_get_contents($url));
          $cnt = 0;
          foreach($aCandidate->personal_data_candidate as $personal_data_candidate) {
            $url = URLBASE.$personal_data_candidate .'?format=json&username='. get_option('candideitv2_username') .'&api_key='. get_option('candideitv2_api_key');
            $aPersonalDataCandidate = json_decode(file_get_contents($url));
            
            $url = URLBASE.$aPersonalDataCandidate->personal_data .'?format=json&username='. get_option('candideitv2_username') .'&api_key='. get_option('candideitv2_api_key');
            $aPersonalData = json_decode(file_get_contents($url));

            $aData[$cnt]['label'] = $aPersonalData->label;
            $aData[$cnt]['value'] = $aPersonalDataCandidate->value;

            $cnt++;
          }

          foreach($aCandidate->links as $link) {
            $url = URLBASE.$link .'?format=json&username='. get_option('candideitv2_username') .'&api_key='. get_option('candideitv2_api_key');
            $aSocialLink = json_decode(file_get_contents($url));

            $aSocial[$aSocialLink->name] = $aSocialLink->url;
          }

          $antecedentes = array();
          
          foreach($aCandidate->backgrounds_candidate as $bgc) {
            $url = URLBASE.$bgc.'?format=json&username='. get_option('candideitv2_username') .'&api_key='. get_option('candideitv2_api_key');
            $bgCandidate = json_decode(file_get_contents($url));
            
            $url = URLBASE.$bgCandidate->background.'?format=json&username='. get_option('candideitv2_username') .'&api_key='. get_option('candideitv2_api_key');
            $background = json_decode(file_get_contents($url));
            
            $url = URLBASE.$background->background_category.'?format=json&username='. get_option('candideitv2_username') .'&api_key='. get_option('candideitv2_api_key');
            $bg_category = json_decode(file_get_contents($url));
            
            $antecedentes[$bg_category->name][$background->name] = $bgCandidate->value;
          }
          include 'html/ficha.php';
        } else {
          include 'html/index.php';
        }
        
      break;

      case 'comparador':
        $url = URLBASE.API_VERSION.'election/'. get_option('candideitv2_election_id') .'/?format=json&username='. get_option('candideitv2_username') .'&api_key='. get_option('candideitv2_api_key');
        $get_status = get_headers($url);

        if($get_status[0] == 'HTTP/1.1 200 OK' OR $get_status[0] == 'HTTP/1.0 200 OK') {
          $election = json_decode(file_get_contents($url));
          $cnt = 0;
          foreach($election->candidates as $c) {
            $url = URLBASE. $c .'?format=json&username='. get_option('candideitv2_username') .'&api_key='. get_option('candideitv2_api_key');
            $candidate = json_decode(file_get_contents($url));

            $aCandidates[$cnt]['id'] = $candidate->id;
            $aCandidates[$cnt]['name'] = $candidate->name;
            $aCandidates[$cnt]['photo'] = $candidate->photo;
            $cnt++;
          }

          $cnt = 0;
          foreach($election->categories as $cat) {
            $url = URLBASE. $cat .'?format=json&username='. get_option('candideitv2_username') .'&api_key='. get_option('candideitv2_api_key');
            $categorie = json_decode(file_get_contents($url));

            $aCategories[$cnt]['id'] = $categorie->id;
            $aCategories[$cnt]['name'] = $categorie->name;

            $cnt_y = 0;
            foreach($categorie->questions as $question) {
              $url = URLBASE. $question .'?format=json&username='. get_option('candideitv2_username') .'&api_key='. get_option('candideitv2_api_key');
              $q = json_decode(file_get_contents($url));

              $aCategories[$cnt]['questions'][$cnt_y]['q'] = $q->question;

              foreach($q->answers as $answers) {
                $url = URLBASE. $answers .'?format=json&username='. get_option('candideitv2_username') .'&api_key='. get_option('candideitv2_api_key');
                $a = json_decode(file_get_contents($url));
                if( in_array('/api/v2/candidate/'.$cid.'/', $a->candidates)) { $aCategories[$cnt]['questions'][$cnt_y]['a'] = $a->caption; } 
              }

              $cnt_y++;
            }

            $cnt++;
          }

          if($cid) {
            $url = URLBASE.API_VERSION.'candidate/'. $cid .'/?format=json&username='. get_option('candideitv2_username') .'&api_key='. get_option('candideitv2_api_key');
            $aCandidate = json_decode(file_get_contents($url));
          }
          

          include 'html/comparador.php';
        } else {
          include 'html/index.php';
        }

        
        break;

      default:
        $url = URLBASE.API_VERSION.'election/'. get_option('candideitv2_election_id') .'/?format=json&username='. get_option('candideitv2_username') .'&api_key='. get_option('candideitv2_api_key');
        $get_status = get_headers($url);

        if($get_status[0] == 'HTTP/1.1 200 OK' OR $get_status[0] == 'HTTP/1.0 200 OK') {
          $json_info = file_get_contents($url);
          $aElections = json_decode($json_info);

          foreach ($aElections->candidates as $candidate) {
            $url = URLBASE. $candidate .'?format=json&username='. get_option('candideitv2_username') .'&api_key='. get_option('candideitv2_api_key');
            $json_info = file_get_contents($url);
            $aCandideits[] = json_decode($json_info);
          }
        }
        include 'html/index.php';
      break;
    }
    
  } else {
    return get_the_content();
  }
}
add_filter( 'the_content', 'canv2_loscandidatos' );

function canv2_theme_styles() { 
  $url_bootstrap = 'http://netdna.bootstrapcdn.com/twitter-bootstrap/2.3.2/css/bootstrap-combined.min.css';
  wp_register_style( 'canv2-style-bootstrap', $url_bootstrap , array(), date('Ymd'), 'all' );

  $url_fontawesome = 'http://netdna.bootstrapcdn.com/font-awesome/3.2.1/css/font-awesome.min.css';
  wp_register_style( 'canv2-style-fontawesome', $url_fontawesome , array(), date('Ymd'), 'all' );

  $url_plugin = plugins_url('/css/candideitorg.css', __FILE__);
  wp_register_style( 'canv2-style', $url_plugin , array(), date('Ymd'), 'all' );

  wp_enqueue_style( 'canv2-style-bootstrap' );
  wp_enqueue_style( 'canv2-style-fontawesome' );
  wp_enqueue_style( 'canv2-style' );
}
add_action('wp_enqueue_scripts', 'canv2_theme_styles');

function canv2_load_script() {  
  wp_enqueue_script('canv2-admin_candideitorg', plugin_dir_url(__FILE__).'js/admin_candideitorg.js', array('jquery'));
}
add_action('admin_enqueue_scripts','canv2_load_script');

function canv2_proccess_candidates_ajax() {
  $electionId = filter_var($_POST['electionId'],FILTER_SANITIZE_NUMBER_INT);
  $username = filter_var($_POST['username'],FILTER_SANITIZE_SPECIAL_CHARS);
  $apikey = filter_var($_POST['apikey'],FILTER_SANITIZE_SPECIAL_CHARS);

  $url = URLBASE.API_VERSION.'election/'. $electionId .'/?format=json&username='. $username .'&api_key='. $apikey;
  
  $aData = file_get_contents($url);
  $aElection = json_decode($aData);

  $return_candidates = '<ul>';
  foreach($aElection->candidates as $candidate) {
    $url_candidate = URLBASE.$candidate.'?format=json&username='. $username .'&api_key='. $apikey;
    $candidate = json_decode(file_get_contents($url_candidate));
    $return_candidates .= '<li>'.$candidate->name.'</li>';
  }
  $return_candidates .= '</ul>';

  echo $return_candidates;

  die();
}
add_action('wp_ajax_canv2_get_candidates','canv2_proccess_candidates_ajax');

function my_scripts_method() {
  wp_enqueue_script('canv2-fontawesome', 'http://netdna.bootstrapcdn.com/twitter-bootstrap/2.3.2/js/bootstrap.min.js', array('jquery'));
  wp_enqueue_script( 'custom-script', plugins_url('/js/front_candideitorg.js', __FILE__), array( 'jquery' ) );
}
add_action( 'wp_enqueue_scripts', 'my_scripts_method' );


function canv2_ajaxurl() {
?>
<script data-cfasync="false" type="text/javascript">
  var ajaxurl = '<?php echo admin_url("admin-ajax.php"); ?>';
</script>
<?php
}
add_action('wp_head','canv2_ajaxurl');

// function front
function get_front_candideit_data($candidate_uri=''){
  //uri of a candidate
  $uri = (isset($_POST['uri'])) ? filter_var($_POST['uri'],FILTER_SANITIZE_SPECIAL_CHARS) : $candidate_uri;

  //get image of a candidate
  $url_candidate = URLBASE.$uri.'?format=json&username='. get_option('candideitv2_username') .'&api_key='. get_option('candideitv2_api_key');
  $aDataCandidate = json_decode(file_get_contents($url_candidate));

  $image_candidate = '<div><img src="'.$aDataCandidate->photo.'" border="0" alt="'.$aDataCandidate->name.'"></div>';
  $name_candidate = '<div class="row-fluid"><div class="span12"><p>'.$aDataCandidate->name.'</p></div></div>';

  // get social links (twitter, facebook, g+)
  $social_media = '<ul class="inline">';
  foreach($aDataCandidate->links as $link) {
    $url_link = URLBASE.$link.'?format=json&username='. get_option('candideitv2_username') .'&api_key='. get_option('candideitv2_api_key');
    $aDataLink = json_decode(file_get_contents($url_link));

    $social_media .= '<li><a href="'.$aDataLink->url.'" target="_blank">'.$aDataLink->name.'</a></li>';
  }
  $social_media .= '</ul>';

  $img_social = '<div class="row-fluid">';
  $img_social .= '<div class="span3">'.$image_candidate.'</div>';
  $img_social .= '<div class="span6"><h2>'.$aDataCandidate->name.'</h2></div>';
  $img_social .= '<div class="span3">'.$social_media.'</div>';
  $img_social .= '</div>';

  //get personal data (age, profession, etc)
  $canideit_personal_data = '<div class="row-fluid"><table class="table table-striped table-bordered">';
  foreach($aDataCandidate->personal_data_candidate as $personal_data) {
    $url_personal_data = URLBASE.$personal_data.'?format=json&username='. get_option('candideitv2_username') .'&api_key='. get_option('candideitv2_api_key');
    $aDataPersonalData = json_decode(file_get_contents($url_personal_data));

    $url = URLBASE.$aDataPersonalData->personal_data.'?format=json&username='. get_option('candideitv2_username') .'&api_key='. get_option('candideitv2_api_key');
    $aDataPersonalDetail = json_decode(file_get_contents($url));

    $canideit_personal_data .= '<tr><td>'.$aDataPersonalDetail->label.'</td><td>'.$aDataPersonalData->value.'</td></tr>';
  }
  $canideit_personal_data .= '</table></div>';

  //get election for obtain category, question related to category and answer for a candidate
  $url_election = URLBASE.API_VERSION.'election/'.get_option('candideitv2_election_id').'/?format=json&username='. get_option('candideitv2_username') .'&api_key='. get_option('candideitv2_api_key');
  $aElection = json_decode(file_get_contents($url_election));
  $categories = '<ul>';
  foreach($aElection->categories as $category) {
    $url_categorie = URLBASE.$category.'?format=json&username='. get_option('candideitv2_username') .'&api_key='. get_option('candideitv2_api_key');
    $aCategories = json_decode(file_get_contents($url_categorie));
    $categories .= '<li>'.$aCategories->name;
    $categories .= '<ul>';
    foreach ($aCategories->questions as $question) {
      $url_question = URLBASE.$question.'?format=json&username='. get_option('candideitv2_username') .'&api_key='. get_option('candideitv2_api_key');
      $aQuestion = json_decode(file_get_contents($url_question));
      $categories .= '<li>'.$aQuestion->question;
      foreach($aQuestion->answers as $answer) {
        foreach($aDataCandidate->answers as $answer_can) {
          if( $answer == $answer_can) {
            $url_answer = URLBASE.$answer.'?format=json&username='. get_option('candideitv2_username') .'&api_key='. get_option('candideitv2_api_key');
            $aAnswer = json_decode(file_get_contents($url_answer));
            $categories .= '<ul><li>'.$aAnswer->caption.'</li></ul>';
          }
        }//enf foreach
      }//enf foreach
      $categories .= '</li>';
    }//enf foreach
    $categories .= '</ul></li>';
  }//enf foreach
  $categories .= '</ul>';

  //return all data
  //create a filter to delimitate information when a user compare a candidate
  if(!$candidate_uri) {
    $div_container_candidate = '<div class="row-fluid"><div class="span12">';
    $div_container_candidate .= '<a href="#" class="backto-candidates">volver</a> / <a href="#" class="versus" data-candidate-uri="'. $uri .'">frente a frente</a>';
    $div_container_candidate .= '<div class="candidate-vs-one">';
  } else {
    $div_container_candidate = '<div class="information-about-candidate-vs">';
  }
  
  $div_container_candidate .= $img_social.$canideit_personal_data.$categories.$question_answer;

  if(!$candidate_uri) {
    $div_container_candidate .= '</div></div></div>';
  } else {
    $div_container_candidate .= '</div>';
  }
  
  echo $div_container_candidate;
  //mandatory
  die();
}
add_action('wp_ajax_get_front_candideit_data','get_front_candideit_data');
add_action('wp_ajax_nopriv_get_front_candideit_data', 'get_front_candideit_data');

function get_front_candideit_select(){
  $uri_exclude = ( isset($_POST['exclude_candidate_uri']) ) ? filter_var($_POST['exclude_candidate_uri'],FILTER_SANITIZE_SPECIAL_CHARS) : '' ;

  $url_election = URLBASE.API_VERSION.'election/'. get_option('candideitv2_election_id') .'/?format=json&username='. get_option('candideitv2_username') .'&api_key='. get_option('candideitv2_api_key');
  $aDataElection = json_decode(file_get_contents($url_election));
  
  $select_candidates = '<select name="select-candidate-uri">';
  $select_candidates .= '<option value="0">Selecciona un candidato</option>';
  foreach($aDataElection->candidates as $uri_candidate) {
    if( $uri_exclude != $uri_candidate ) {
      $url_candidate = URLBASE.$uri_candidate.'?format=json&username='. get_option('candideitv2_username') .'&api_key='. get_option('candideitv2_api_key');
      $aDataCandidate = json_decode(file_get_contents($url_candidate));
      $select_candidates .= '<option value="'.$uri_candidate.'">'.$aDataCandidate->name.'</option>';   
    }
  }
  $select_candidates .= '</select>';

  echo $select_candidates;

  die();
}
add_action('wp_ajax_get_front_candideit_select','get_front_candideit_select');
add_action('wp_ajax_nopriv_get_front_candideit_select', 'get_front_candideit_select');

function get_data_candidate_vs() {
  $candidate_uri = ( isset($_POST['candidate_uri']) ) ? filter_var($_POST['candidate_uri'],FILTER_SANITIZE_SPECIAL_CHARS) : '';
  
  get_front_candideit_data($candidate_uri);

  die();
}
add_action('wp_ajax_get_data_candidate_vs','get_data_candidate_vs');
add_action('wp_ajax_nopriv_get_data_candidate_vs', 'get_data_candidate_vs');

function get_candidate_data() {
  $candidate_id = ( isset($_POST['first_candidate']) ) ? filter_var($_POST['first_candidate'],FILTER_SANITIZE_NUMBER_INT) : filter_var($_POST['second_candidate'],FILTER_SANITIZE_NUMBER_INT);
  $class_name = ( isset($_POST['first_candidate']) ) ? 'first_candidate' : 'second_candidate';

  $url = URLBASE.API_VERSION.'candidate/'. $candidate_id .'/?format=json&username='. get_option('candideitv2_username') .'&api_key='. get_option('candideitv2_api_key');
  $aCandidate = json_decode(file_get_contents($url));

  $retorna = '<div class="row-fluid '.$class_name.'" style="margin-bottom: 10px">
        <div class="span4">
          <img src="'. $aCandidate->photo .'" alt="'. $aCandidate->name .'">
        </div>
        <div class="span8">
          <h4>'. $aCandidate->name .'</h4>
        </div>
      </div>';

  $url = URLBASE.API_VERSION.'election/'. get_option('candideitv2_election_id') .'/?format=json&username='. get_option('candideitv2_username') .'&api_key='. get_option('candideitv2_api_key');
  $election = json_decode(file_get_contents($url));
  $cnt = 0;
  foreach($election->categories as $cat) {
    $url = URLBASE. $cat .'?format=json&username='. get_option('candideitv2_username') .'&api_key='. get_option('candideitv2_api_key');
    $categorie = json_decode(file_get_contents($url));

    $aCategories[$cnt]['id'] = $categorie->id;
    $aCategories[$cnt]['name'] = $categorie->name;

    $cnt_y = 0;
    foreach($categorie->questions as $question) {
      $url = URLBASE. $question .'?format=json&username='. get_option('candideitv2_username') .'&api_key='. get_option('candideitv2_api_key');
      $q = json_decode(file_get_contents($url));

      $aCategories[$cnt]['questions'][$cnt_y]['q'] = $q->question;

      foreach($q->answers as $answers) {
        $url = URLBASE. $answers .'?format=json&username='. get_option('candideitv2_username') .'&api_key='. get_option('candideitv2_api_key');
        $a = json_decode(file_get_contents($url));
        if( in_array('/api/v2/candidate/'.$candidate_id.'/', $a->candidates)) { $aCategories[$cnt]['questions'][$cnt_y]['a'] = $a->caption; } 
      }

      $cnt_y++;
    }

    $cnt++;
  }

  $retorna .= '<div class="row-fluid '.$class_name.'">
        <div class="span12">
          <ul id="myTab" class="nav nav-pills">
            ';
            $cnt = 1;
            foreach($aCategories as $cat) {
              $active = ($cnt==1) ? 'class="active"' : '';
              $retorna .= '<li '.$active.'><a href="#'.strtolower( str_replace(' ', '-', trim($cat['name'])) ).'-'.$candidate_id.'" data-toggle="tab">'.ucfirst(strtolower($cat['name'])).'</a></li>';
              $cnt++;
            }
            
  $retorna .= '</ul>
          <div class="tab-content">';
            
            $cnt = 1;
            foreach($aCategories as $cat) {
              
              $retorna .= '<div class="tab-pane '. (($cnt==1) ? "active" : "") .'" id="'. strtolower( str_replace(' ', '-', trim($cat['name'])) ) .'-'.$candidate_id.'">
                <table class="table table-striped">';
                  
                  foreach($cat['questions'] as $question) {
                  
                  $retorna .= '<tr>
                    <td><strong>'. $question['q'] .'</strong></td>
                  </tr>
                  <tr>
                    <td><em>'. ((empty($question['a'])) ? 'N/I' : $question['a']) .'</em></td>
                  </tr>';
                  
                  }
                  
              $retorna .= '</table>
              </div>';
              
              $cnt++;
            }
            
  $retorna .= '</div>
        </div>
      </div>';

  echo $retorna;
  die();
}
add_action('wp_ajax_get_candidate_data','get_candidate_data');
add_action('wp_ajax_nopriv_get_candidate_data', 'get_candidate_data');