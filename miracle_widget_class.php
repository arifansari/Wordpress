<?php

class Miracle_WidgetEnhancement
{
    function Miracle_WidgetEnhancement()
    {
        $this->init();
    }

    function init()
    {
        if (is_admin())
        {
            add_filter('widget_update_callback', array($this, 'miracle_widget_update'), 10, 2);
            add_filter('in_widget_form', array($this, 'miracle_in_widget_form_extend'), 10, 3);
        }
        
        add_filter('init', array($this, 'init_init'));
    }
    
    function init_init()
    {
        add_filter('dynamic_sidebar_params', array($this, 'miracle_apply_widget_styles'));
        add_filter('widget_display_callback', array($this, 'miracle_check_widget_visibility'), 10, 3);
        
    }
    
    // Add new fields into widgets
    function miracle_in_widget_form_extend($widget, $return, $instance)
    {
        $this->miracle_add_visibility_fields($widget, $instance);
        $this->miracle_add_style_fields($widget, $instance);
        echo '<div class="clear"></div>';

    }
    
    // Updating the widget options
    function miracle_widget_update($instance, $new_instance)
    {
        echo 'update';
        $instance = $this->miracle_update_visibility_fields($instance, $new_instance);
        $instance = $this->miracle_update_style_fields($instance, $new_instance);
        return $instance;
    }
    
    function miracle_update_visibility_fields($instance, $new_instance)
    {
        //check if conditions should apply to ATW only
        if ('advanced_text' == get_class($widget))
            return;

        $instance['action'] = $new_instance['action'];
        $instance['show'] = $new_instance['show'];
        $instance['slug'] = $new_instance['slug'];
        $instance['suppress_title'] = $new_instance['suppress_title'];

        return $instance;
    }

    function miracle_update_style_fields($instance, $new_instance)
    {
        $instance['container_id'] = $new_instance['container_id'];
        $instance['container_class'] = $new_instance['container_class'];
        $instance['template_class'] = $new_instance['template_class'];

        return $instance;
    }

    function miracle_apply_widget_styles($params)
    {
        global $wp_registered_widgets;
        
        
        $widget_id = $params[0]['widget_id'];
        $widget_obj = $wp_registered_widgets[$widget_id];
        $widget_opt = get_option($widget_obj['callback'][0]->option_name);
        $widget_num = $widget_obj['params'][0]['number'];
        
        //set width and height
        //$widget_obj['callback'][0]->control_options['width'] = 400;
        //$widget_obj['callback'][0]->control_options['height'] = 400;
        

        if(isset( $widget_opt[$widget_num]['container_id'])) $container_id = $widget_opt[$widget_num]['container_id'];
              if(isset( $widget_opt[$widget_num]['container_class']))  $container_class = $widget_opt[$widget_num]['container_class'];
         if(isset( $widget_opt[$widget_num]['template_class']))  $template_class = $widget_opt[$widget_num]['template_class'];

        if (isset( $container_id))
            $params[0]['before_widget'] = preg_replace('/id=".*?"/', "id=\"{$container_id}\"",
                $params[0]['before_widget'], 1);
        if (isset($container_class))
            $params[0]['before_widget'] = preg_replace('/class="/', "class=\"{$container_class} ",
                $params[0]['before_widget'], 1);
        if (isset($template_class))
            $params[0]['before_widget'] = preg_replace('/class="/', "class=\"{$template_class} ",
                $params[0]['before_widget'], 1);

        return $params;
    }
    
    function miracle_check_widget_visibility($instance, $widget_obj = null, $args = false)
    {
        global $post;
        $conditions = $this->miracle_widget_visibility_conditions();

        if (false !== $widget_obj && is_object($widget_obj))
        {
            if ('advanced_text' == get_class($widget_obj))
                return $instance;
        }

        if (isset($instance['suppress_title']) && false != $instance['suppress_title'])
        {
            unset($instance['title']);
        }

        if (isset($instance['action']))
        {
            $action = $instance['action'];
        } else
        {
            return $instance;
        }

        if (isset($instance['show']))
            $show = $instance['show'];

        if (isset($instance['slug']))
            $slug = $instance['slug'];


        /* Do the conditional tag checks. */
        $arg = explode('|', $slug);

        //Checking if $show in not numeric - in that case we have older version conditions
        $code = $conditions[$show]['code'];

        //echo $show.' '.$slug.' '.$code;
        //exit;
        $num = count($arg);
        $i = 1;

        foreach ($arg as $k => $v)
        {
            $ids = explode(",", $v);
            $str = '';
            $values = array();

            //wrap each value into quotation marks
            foreach ($ids as $val)
            {
                if ($val != "")
                    $values[] = '"' . $val . '"';
            }


            $str = (1 == count($values)) ? $values[0] : "array(" . implode(',', $values) .
                ")";


            //if multiple values, then put them into an array
            if (1 < $num)
            {
                $code = str_replace('$arg' . $i, $str, $code);
            } else
            {
                $code = str_replace('$arg', $str, $code);
            }
            $i++;
        }

        if ($code != false && $action == "1")
        {
            $code = "if($code){ return true; }else{ return false; }";

            if (eval($code))
            {
                return $instance;
            }
        } elseif ($code != false)
        {
            $code = "if($code){ return false; }else{ return true; }";
            if (eval($code))
            {
                return $instance;
            }
        }

        return false;
    }

    function miracle_widget_visibility_conditions()
    {
        $condition = array(
            array(
                'name' => 'All',
                'code' => 'true',
                ),
            array(
                'name' => 'Home Page',
                'code' => 'is_home()',
                ),
            array(
                'name' => 'Front Page',
                'code' => 'is_front_page()',
                ),
            array(
                'name' => 'Page',
                'code' => 'is_page($arg)',
                ),
            array(
                'name' => 'Single Post',
                'code' => 'is_single($arg)',
                ),
            array(
                'name' => 'Post in Category',
                'code' => 'in_category($arg)',
                ),
            array(
                'name' => 'Category',
                'code' => 'is_category($arg)',
                ),
            array(
                'name' => 'Blog',
                'code' => 'is_home() || is_single() || is_archive()',
                ),
            array(
                'name' => 'Search Results Page',
                'code' => 'is_search()',
                ),
            array(
                'name' => 'Child of Page ID',
                'code' => '(int)$arg == $post->post_parent',
                )
        );

        return $condition;
    }

    function miracle_add_visibility_fields($widget, $instance = false)
    {
        //check if conditions should apply to ATW only
        if ('advanced_text' == get_class($widget))
            return;

        $conditions = $this->miracle_widget_visibility_conditions();

        if (!$instance)
        {
            $widget_settings = get_option($widget->option_name);
            $instance = $widget_settings[$widget->number];
        }

        $allSelected = $homeSelected = $postSelected = $postInCategorySelected = $pageSelected =
            $categorySelected = $blogSelected = $searchSelected = false;
        switch ($instance['action'])
        {
            case "1":
                $showSelected = true;
                break;
            case "0":
                $dontshowSelected = true;
                break;
        }

?>			
	<div class="atw-conditions">
	<strong><?php _e('Widget Visibility:', $this->textdomain);?></strong>
    <br /><br />
	<label for="<?php echo $widget->get_field_id('action');?>"  title="<?php _e('Show only on specified page(s)/post(s)/category. Default is All', $this->textdomain); ?>" style="line-height:35px;">		
	<select name="<?php echo $widget->get_field_name('action');?>">
        <option value="1" <?php echo ($showSelected)? 'selected="selected"':'';?>><?php _e('Show', $this->textdomain);?></option>
			<option value="0" <?php echo ($dontshowSelected)? 'selected="selected"':'';?>><?php _e('Do NOT show', $this->textdomain);?></option>
		</select> 
        <?php _e('on', $this->textdomain); ?>
		<select name="<?php echo $widget->get_field_name('show');?>" id="<?php echo $widget->get_field_id('show');?>">
            <?php
            if (is_array($conditions) && !empty($conditions))
            {
                foreach ($conditions as $k => $item)
                {
                    $output .= '<option label="' . $item['name'] . '" value="' . $k . '"' . selected($instance['show'],
                        $k) . '>' . $item['name'] . '</option>';
                }
            }
            echo $output;
            ?>
		</select>
	</label>
	<br/> 
	<label for="<?php echo $widget->get_field_id('slug'); ?>"  title="<?php _e('Optional limitation to specific page, post or category. Use ID, slug or title.',$this->textdomain);?>"><?php _e('Slug/Title/ID:',$this->textdomain); ?> 
		<input type="text" style="width: 99%;" id="<?php echo $widget->get_field_id('slug');?>" name="<?php echo $widget->get_field_name('slug');?>" value="<?php echo htmlspecialchars($instance['slug']); ?>" />
	</label>
	<?php
        if ($postInCategorySelected)
            _e("<p>In <strong>Post In Category</strong> add one or more cat. IDs (not Slug or Title) comma separated!</p>",$this->textdomain);
    ?>
	<br />
	<label for="<?php echo $widget->get_field_id('suppress_title'); ?>"  title="<?php _e('Do not output widget title in the front-end.', $this->textdomain);?>">
		<input id="<?php echo $widget->get_field_name('suppress_title'); ?>" name="<?php echo $widget->get_field_name('suppress_title'); ?>" type="checkbox" value="1" <?php checked($instance['suppress_title'], '1', true);?> />
        <?php _e('Suppress Title Output', $this->textdomain); ?>
	</label>
	</div>
<?php

        $return = null;
    }

    function miracle_add_style_fields($widget, $instance = false)
    {
        if (!$instance)
        {
            $widget_settings = get_option($widget->option_name);
            $instance = $widget_settings[$widget->number];
        }

        $container_id = $instance['container_id'];
        $container_class = $instance['container_class'];
        $menu_class = $instance['template_class'];

        $t = array('' => '');
        $templates = apply_filters('miracle_widgets_tempaltes', $t);

?>
<div class="atw-conditions">
    <strong><?php _e('Styling Widget:', $this->textdomain);?></strong><br /><br />
<p>
    <label for="<?php echo $widget->get_field_id('container_id'); ?>"><?php _e('Container ID:', $this->textdomain);?></label>
    <input style="width: 100%;" id="<?php echo $widget->get_field_id('container_id'); ?>" name="<?php echo $widget->get_field_name('container_id'); ?>" type="text" value="<?php echo $container_id;?>" />
    <small><?php _e('The ID that is applied to the container.', $this->textdomain);?></small>
</p>
<p>
    <label for="<?php echo $widget->get_field_id('container_class'); ?>"><?php _e('Container Class:', $this->textdomain);?></label>
    <input style="width: 100%;" id="<?php echo $widget->get_field_id('container_class');?>" name="<?php echo $widget->get_field_name('container_class');?>" type="text" value="<?php echo $container_class;?>" />
    <small><?php _e('The CssClass that is applied to the container.', $this->textdomain);?></small>
</p>
<p>
    <label for="<?php echo $widget->get_field_id('template_class');?>"><?php _e('Template:', $this->textdomain);?></label><br />
    <select style="width:100%;" id="<?php echo $widget->get_field_id('template_class'); ?>" name="<?php echo $widget->get_field_name('template_class');?>"> 
    <?php
        foreach ($templates as $key => $value)
        {
    ?>
        <option value="<?php echo $key;?>"<?php selected($menu_class, $key);?>><?php echo $value; ?></option>
    <?php
        }
    ?>
    </select>
    <small><?php _e('CSS class to use for the ul element which forms the menu.', $this->textdomain);?></small>
</p>
</div>		
<?php
    }
}
new miracle_WidgetEnhancement();
?>