<?php

/*
=====================================================
 Freeform auto reply
-----------------------------------------------------
 http://www.intoeetive.com/
-----------------------------------------------------
 Copyright (c) 2013 Yuri Salimovskiy
=====================================================
 This software is intended for usage with
 ExpressionEngine CMS, version 2.0 or higher
=====================================================
 File: ext.freeform_auto_reply.php
-----------------------------------------------------
 Purpose: Send automatic response when Freeform submission is moderated
=====================================================
*/

if ( ! defined('BASEPATH'))
{
	exit('Invalid file request');
}


class Freeform_auto_reply_ext {

	var $name	     	= "Freeform auto reply";
	var $version 		= 0.1;
	var $description	= 'Send automatic response when Freeform submission is moderated';
	var $settings_exist	= 'y';
	var $docs_url		= 'http://githib.com/intoeetive/freeform_auto_reply/README';
    
    var $settings 		= array();
    
	/**
	 * Constructor
	 *
	 * @param 	mixed	Settings array or empty string if none exist.
	 */
	function __construct($settings = '')
	{
		$this->EE =& get_instance();
		$this->settings = $settings;
	}
    
    /**
     * Activate Extension
     */
    function activate_extension()
    {
        
        $hooks = array(
			array(
    			'hook'		=> 'freeform_module_insert_begin',
    			'method'	=> 'send_reply',
    			'priority'	=> 10
    		),
            
    	);
    	
        foreach ($hooks AS $hook)
    	{
    		$data = array(
        		'class'		=> __CLASS__,
        		'method'	=> $hook['method'],
        		'hook'		=> $hook['hook'],
        		'settings'	=> '',
        		'priority'	=> $hook['priority'],
        		'version'	=> $this->version,
        		'enabled'	=> 'y'
        	);
            $this->EE->db->insert('extensions', $data);
    	}	
        
    }
    
    /**
     * Update Extension
     */
    function update_extension($current = '')
    {
    	if ($current == '' OR $current == $this->version)
    	{
    		return FALSE;
    	}
    	
    	$this->EE->db->where('class', __CLASS__);
    	$this->EE->db->update(
    				'extensions', 
    				array('version' => $this->version)
    	);
    }
    
    
    /**
     * Disable Extension
     */
    function disable_extension()
    {
    	$this->EE->db->where('class', __CLASS__);
    	$this->EE->db->delete('extensions');
    }
    
    
    
    function settings()
    {
        $settings = array();
        
        $this->EE->load->add_package_path(PATH_THIRD.'freeform/');
        $this->EE->lang->loadfile('freeform');  
        
        //get list of Freeform statuses
        require_once PATH_THIRD.'freeform/data.freeform.php';

		$this->EE->FF_DATA = new Freeform_data();
        
        $ff_statuses = $this->EE->FF_DATA->get_form_statuses();
        
        foreach ($ff_statuses as $status_key=>$status_name)
        {
            //$settings[$status_key.'_enable']    = array('r', array('y' => 'Yes', 'n' => 'No'), 'n');
            $settings[$status_key.'_subject']    = array('i', '', '');
            $settings[$status_key]    = array('t', array('rows' => '20'), '');
        }
        
        
        $this->EE->load->model('freeform_field_model');
		$available_fields = $this->EE->freeform_field_model->get();
        
        $fields = array();
        foreach ($available_fields as $fld_arr)
        {
            $fields[$fld_arr['field_name']] = $fld_arr['field_label'];
        }
        
        $settings['trigger_fields'] = array('c', $fields, array());
        
        $this->EE->load->remove_package_path(PATH_THIRD.'freeform/');
        
        return $settings;
    }
    
    
    function send_reply($field_input_data, $entry_id, $form_id, $obj)
    {
        if ($entry_id==0) return $field_input_data; //fresh form
        
        //otherwise, it's an edit
        
        //check that template and email are not empty
        $trigger = false;
        foreach ($this->settings['trigger_fields'] as $trigger_field)
        {
            if ($field_input_data["$trigger_field"]=='y')
            {
                $trigger = true;
                continue;
            }
        }
        if ($trigger==false) return $field_input_data;
        
        if ($field_input_data["email"]=='')  return $field_input_data;
        
        $message = trim($this->settings["{$field_input_data['status']}"]);
        $subject = trim($this->settings["{$field_input_data['status']}_subject"]);
        
        if ($message=='' || $subject=='') return $field_input_data;
        
        require_once PATH_THIRD.'freeform/data.freeform.php';
		$this->EE->FF_DATA = new Freeform_data();
        
        $form_info = $this->EE->FF_DATA->get_form_info($form_id);
        $user_notification_id = $form_info['user_notification_id'];
        
        $notification_info = $this->EE->FF_DATA->get_notification_info_by_id($user_notification_id);
        
        $this->EE->load->library('email');

		$this->EE->email->wordwrap = true;
        $this->EE->email->mailtype = 'text';

		$message = $this->EE->functions->var_swap($message, $field_input_data);
		$subject = $this->EE->functions->var_swap($subject, $field_input_data);

		$this->EE->email->EE_initialize();
		$this->EE->email->from($notification_info['from_email'], $notification_info['from_name']);	
        if ($notification_info['reply_to_email']!='')
        {
            $this->EE->email->reply_to($notification_info['reply_to_email']);
        }
		$this->EE->email->to($field_input_data['email']); 
		$this->EE->email->subject($subject);	
		$this->EE->email->message($message);		
		$this->EE->email->Send();
        
        //$debug = $this->EE->email->print_debugger();
        
        //echo $debug;
        
        return $field_input_data;
    }
    

 
    
    
  

}
// END CLASS
