<?php

defined('BASEPATH') OR exit('No direct script access allowed');
define("REC_ORDER_DIR", "../data/order/Receiving/");

class Receiving extends Application
{

	function __construct()
	{
		parent::__construct();
        $this->load->model("Order");
	}
	
	

	/**
	 * Homepage for our app
	 */
	public function index()
	{
        //check user role, redirect to dashboard if incorrect role
        $userrole = $this->session->userdata('userrole');
        if ($userrole !== "Admin" && $userrole !== 'User') {
            redirect('/', 'refresh');
        }

		// this is the view we want shown
		$this->data['pagebody'] = 'receiving_list';
        $this->data['summary'] = "<a href ='/receiving/summary'>Summary</a>";

		// build the list of authors, to pass on to our view
		$source = $this->Materials->all();

		$this->data['form_open'] = form_open('receiving/post');
		
		//$test = $this->Transactions->getMaterials();

        // Set table headers

        $items[] = array('Name','Cost/Case' ,'Stocked Cases', 'Cases Recieved');

        foreach ($source as $record)
        {
			$text_data = array('name' => $record->id,'type' => 'number', 'value' => '0', 'min' => '0');
			$case = $record->amount / $record->itemPerCase;
            $items[] = array ( '<a href="/receiving/get/' .
                               $record->id. '">' .
                               $record->name. '</a>',
               $this->toDollars($record->price), floor($case) ,form_input($text_data, "", "class='input'"));
        }

        $this->data['Materials_table'] = $this->table->generate($items);
		
		$this->data['order_button'] = form_submit('', 'Receive', "class='submit'");
		
		$this->data['clear_data'] = form_reset('','Clear', "class='submit'");
		
		$this->data['form_close'] = form_close();
		$this->render();
		

	}

	public function get($id) {
		
		$this->data['pagebody'] = 'receiving_single';
		
		$record = $this->Materials->get($id);
		
		$items[] = array('Name','Items Per Case' ,'Total Stocked Items');
		$items[] = array($record->name,$record->itemPerCase ,$record->amount);
		
		$this->data['Materials_table'] = $this->table->generate($items);
		
		
		$this->data['itemName'] = ($record->name);

		$this->render();
	}
	
	public function post()
    {
		
		$this->data['pagebody'] = 'receiving_result';
		
		$empty = "NOTHING WAS RECEIVED!";
		$empty = "<b>" . $empty . "</b><br>Please try again!";
	
		$items[] = array('Ordered Items', '# Ordered Cases','Cost');
		
        //XML
        $order = new Order();
        $order->setType("Receiving");
                

		$j = false;
        $total = 0;

		foreach($_POST as $post_id => $cases)
		{
			if($cases != "" && $cases != 0){
				$source = $this->Materials->get($post_id);
				$items[] = array($source->name,$cases,$source->price * $cases);

				$source->amount += $source->itemPerCase * $cases;
				$this->Materials->update($source);

				$j = true;

                $total += $source->price * $cases;
                                
                //XML
                $order->addItem($post_id, $cases);
			}
		
        }

        $outcome[] = array('line' => "<br><strong>Grand Total:</strong> " . $this->toDollars($total));
        $this->data['result'] = $outcome;


		if($j == false){
			$this->data['Materials_table'] = $empty;
		} else {
             //XML
            $order->saveOrder();
			$this->data['Materials_table'] = $this->table->generate($items);
		}

		$this->render();

    }

	public function clear() {
		$this->Materials->clear();
	}
        
    public function summary() {
        // identify all of the order files
        $type = "Receiving";
        
        $this->load->helper('directory');
        $candidates = directory_map(REC_ORDER_DIR);
        $parms = array();
        sort($candidates, SORT_NUMERIC);
        foreach ($candidates as $filename) {
           // restore that order object
           $order = new Order();
           $order->loadXML(REC_ORDER_DIR . $filename);
        // setup view parameters
           $parms[] = array(
               'number' => $order->number,
               'type' => $order->type,
               'datetime' => $order->datetime
            );
        }
        
        $this->data['type'] = $type;
        $this->data['orders'] = $parms;
        $this->data['pagebody'] = 'summary';
        $this->render('template');  // use the default template
    }
    
    public function examine($which) {
        $order = new Order();
        $order->loadXML(REC_ORDER_DIR . $which . '.xml');
        $stuff = $order->generateReceipt();
        $this->data['receipt'] = $this->parsedown->parse($stuff);
        $this->data['pagebody'] = 'receipt';
        $this->render();
    }
}
