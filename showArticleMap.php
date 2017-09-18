<?php
/*
Plugin Name: Show Article Map
Plugin URI: https://www.naenote.net/entry/show-article-map
Description: Visualize internal link between posts
Author: NAE
Version: 0.1
Author URI: https://www.naenote.net/entry/show-article-map
License: GPL2
*/

/*  Copyright 2017 NAE (email : @__NAE__)
 
    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
     published by the Free Software Foundation.
 
    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.
 
    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/


function nae_insert_str($text, $insert, $num){
    $returnText = $text;
    $text_len = mb_strlen($text, "utf-8");
    $insert_len = mb_strlen($insert, "utf-8");
    for($i=0; ($i+1)*$num<$text_len; $i++) {
        $current_num = $num+$i*($insert_len+$num);
        $returnText = preg_replace("/^.{0,$current_num}+\K/us", $insert, $returnText);
    }
    return $returnText;
}

function nae_get_dataset (){
    $args = array(
        'posts_per_page' => -1,
        'post_type'        => 'post',
        'post_status'      => 'publish'
    );
    $post_array = get_posts( $args );
    $nodes = [];
    $edges = [];

    foreach ($post_array as $post) {
        $category = get_the_category($post->ID);
        $root_category_ID = array_pop(get_ancestors( $category[0]->cat_ID, 'category' ));
        $root_category = get_category($root_category_ID);

        $nodes[] = [
            'id' => $post->ID,
            'label' => nae_insert_str(urldecode($post->post_name), "\r\n", 20),
            'group' => urldecode($root_category->slug),
            'title' => '<a href="'.get_permalink($post).'" target="_blank">'.$post->post_title.'</a>'
        ];
        $html = do_shortcode($post->post_content);
        $dom = new DOMDocument;
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new DOMXPath($dom);

        $query = "//a[
            @href != ''
            and not(starts-with(@href, '#'))
            and normalize-space() != ''
        ]";

        foreach ($xpath->query($query) as $node) {
            $href = $xpath->evaluate('string(@href)', $node);
            $linked_post_id = url_to_postid($href);
            if ($linked_post_id != 0 && !in_array(['from'=>$post->ID,'to'=>$linked_post_id],$edges )){
                $edges[] = [
                    'from' => $post->ID,
                    'to' => $linked_post_id
                ];
            }
        }
    }
    return "[".json_encode($nodes).",".json_encode($edges)."]";
}

function nae_echo_article_map(){
    $dataset = nae_get_dataset ();
    $body = <<<EOD
    <div id="searchnode">
      <label for="searchnodequery">Search by node name</label>
      <input id="searchnodequery" name="searchnodequery" size="30" type="text">
      <button id="searchnodebutton" type="submit">Search</button>
    </div>
    <div id="mynetwork" style="width: 100%; height: 800px; border: 1px solid lightgray;"></div>
    <div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/vis/4.20.0/vis.min.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/vis/4.20.0/vis.min.css" rel="stylesheet">
    <script type="text/javascript">
        var dataset = $dataset;
        var nodedata = dataset[0];
        var edgedata = dataset[1];
        // create an array with nodes
        var nodes = new vis.DataSet(nodedata);
        // create an array with edges
        var edges = new vis.DataSet(edgedata);
        // create a network
        var container = document.getElementById('mynetwork');
        // provide the data in the vis format
        data = {  nodes: nodes, edges: edges };
        var options = {
            nodes:{shape:"box"},
            edges:{arrows: {to:{enabled: true, scaleFactor:1, type:'arrow'}}},
            manipulation:{enabled:true},
         };
         
        // initialize your network!
        var network = new vis.Network(container, data, options);
        
        // double click node to open an article
        network.on('doubleClick', function(e){
            var nodeID = e.nodes.toString();
            var url = jQuery(data.nodes.get(nodeID)['title']).attr('href');
            //console.log(jQuery(data.nodes.get(nodeID)['title'])); console.log(url);
            window.open(url,'_blank');
        });
        
        // search node label by query
        jQuery('#searchnodebutton').click(function(){
          var search = jQuery('#searchnodequery').val();

          // serch nodes by node label
          var hitNodes = nodes.get({
            filter:function(item){
              var label = item.label.replace("\\r\\n","");
              return label.indexOf(search) != -1;
            }
          });
          var hitNodeIDs = [];
          for (i=0;i<hitNodes.length;i++) {
            hitNodeIDs.push(hitNodes[i].id);
          };

          // select
          network.selectNodes(hitNodeIDs);
        });
        jQuery('#searchnodequery').keypress(function(e){
          if(e.which == 13){//Enter key pressed
            jQuery('#searchnodebutton').click();//Trigger search button click event
          }
        });
    </script>
    </div>
EOD;
    return $body;
}

add_shortcode( 'show_article_map', 'nae_echo_article_map' );