<?php

class walk_var
    {
    	public $name, $value;
    	public $eqs = array(), $full_poss = false, $poss = array(), $cond = array();	
        public $poss_count;
        public $upper_limit = 23, $lower_limit = 3;
        public $mode= 'normal';
    	private $tree;
    	
    	
    	function display_cond()
    	{
    	    if ($this->mode == 'gemini') {
                $set = [];
    	        foreach ($this->cond['wvars'] as $var) 
    	            {  $set[] = $var->name .  ' '; }
    	       return ' mirrors ' . implode(',',$set) . ' with sum of ' . $this->cond['gap'];
    	    }
    	}
    	
    	function clamp_upper_limit(int $x)
    	{
    	    if ($x < $this->upper_limit) {
    	        $this->upper_limit = $x;
    	    }
    	}
    	
    	function clamp_lower_limit(int $x)
    	{
    	    if ($x > $this->lower_limit) {
    	        $this->lower_limit = $x;
    	    }}
    	
    	function set_mode(string $mode,array $wvars,int $gap)
    	{
    	    $this->mode = $mode;
    	    $this->cond = array('wvars' => $wvars, 'gap' => $gap);
    	}
    	
        function set_static(int $val)
        {
           $this->mode = 'static';
           $this->value = $val;
        }
        
        function set_poss($val)
        {
            $this->value = $val;
            return $this->tree->set_var($this->name,$this->value);
        }
        
    	function __construct(string $name, object $tree)
    	{
    		$this->name = $name;
    		$this->tree = $tree;
    	}
    	function add_eq(int $eq_num,array $vals)
    	{
    		$this->eqs[$eq_num] = $vals;	
    	}
    
        function add_cond(string $var,array $lookups)
        {
            $this->cond[$var] =  $lookups;
        }
    	
    	function unset()
    	{
    	    $this->tree->unset_var($this->name,$this->value);
    	    $this->value = false;
    	}
    	
    	function reset($test)
    	{
    		if ($this->mode == 'static') { return false; }
    		$this->value = false;
    	}
    
    	function assess($taken = false)
    	{
    	    if ($this->mode == 'static') {  
    	        $this->full_poss = array( $this->value);
    	        $this->poss = array($this->value);
    	        return 1; 
    	    }
    	    if ($this->mode == 'gemini') {
    	/*        echo "\n";*/
    	        $out = 0; $gfail = false;
    	        foreach ($this->cond['wvars'] as $var) {
    	            if ($var->value == false) { $gfail = true; }
    	             $out +=  $var->value;
    	       /*      echo $var->name . ' was ' . $var->value . "\n";*/
     	        }
     	        $tv = ($this->cond['gap'] - $out );
     	       
     	        if ($tv < $this->lower_limit) { return 0; }
     	        if ($tv > $this->upper_limit) { return 0; }
     	        if ($gfail) { return 0; }
    	        
    	     /*   echo "\n" . $out . ' total from other places' . "\n";
    	        print_r('gap was ' . $this->cond['gap']);*/
    	        if (!$gfail) {
        	        $this->value = $tv;
        	        $this->full_poss = array( $this->value);
        	        $this->poss = array($this->value);
        	        if ($this->tree->set_var($this->name,$this->value)) {
        	           return 1; 
        	        }
        	        return 0;
        	      }
    	    }
    	    
    	    if (sizeof($this->cond) > 0) {
    	        foreach ($this->cond as $crit_var => $pattern) {
    	            if (isset($this->tree->vals[$crit_var])) {
    	                $this->poss = array($pattern[$this->tree->vals[$crit_var]]);
    	                return 1;
    	            }
    	        }
    	    }
    	    
    		$out  = array_count_values(array_merge(...$this->eqs));
    
    		if ($this->full_poss === false) {
    		    $this->full_poss = array();
    		    $target = sizeof($this->eqs);
    		    foreach ($out as $val => $count)
    		    {
    		        if ($val > $this->upper_limit) { continue; }
    		        if ($val < $this->lower_limit) { continue; }
    		        if ($count == $target) { $this->full_poss[$val] = $val; }
    		    }
    		}
    	
    		if ($taken) {
    		    $this->poss = array_diff($this->full_poss,$taken);
    		} else {
    		    $this->poss = $this->full_poss;
    		    $this->poss_count = sizeof($this->poss);
    		}
    		
    
    		if (sizeof($this->poss) == 1) {
    		    $this->value = implode('',$this->poss);
    		    if ($this->value == 0)  {  $this->poss = array(); return 0;  }
    		    $this->tree->set_var($this->name,$this->value);
    		}
    		return sizeof($this->poss);
    	}
    
    	function display()
    	{
    		echo 'var' . $this->name;
    		print_r($this->eqs);
    	}
    }
    
    class tree_eq
    {
        public $sum;
        public $vars = array();
        
        function __construct(int $sum,array $vars)
        {
            $this->sum = $sum;
            $this->vars = $vars; 
           
        }
       
    }
    
    class tree_constraint
    {
        public $steps = array();
        public $target = 0;
        
        function __construct(int $target, array $steps )
        {
            $this->steps = $steps;
            $this->target = $target;
        }
        function test_one($parts,$tree)
        {
            $total = 0;
            $ato_check = array();
            foreach ($parts as $part) {
                if (isset($tree->vals[$part])) { 
                    $total += $tree->vals[$part];
                } else  {  
                    $ato_check[$part] = $part;
                }
                if ($total == $this->target) { return true; }
                if ($total > $this->target) { return false; }
            }
            if (sizeof($ato_check) == 1) {
                if ($total >= $this->target) { return false; }
                if (isset($tree->poss[$this->target - $total])) {
                    return true;
                }
            }
            if (sizeof($ato_check) == 2) {
                if ($total >= $this->target) { return false; }
                
                foreach ($tree->poss as $x => $y)
                {
                    if (($this->target - $x) == $x) { continue; }
                    if (isset($tree->poss[$this->target - $x])) {
                        return true;
                    }
                }
             }
            
            return false;
        }
        
        function test($tree)
        {
            foreach ($this->steps as $step)
            {
                $parts = str_split($step);
                if ($this->test_one($parts,$tree)) {
               //     echo "\n" . 'passed on ' .  $this->target . ' with ' . $step;
                    return true;
                }

            }
            return false;        
        }

    }
    
    
    class tree_tester
    {
     public $reverse = 0;
     public $run = 0, $best_poss, $no_poss =0;
     public $vars = array();
     public $target_space = '';
     
     public  $tests = array(), $constraints = array();
      public $vals = array();
      public $best_values = array();
      public $poss = array();
     
      function __construct()
      {
          $this->start_time = microtime(true);
          $this->tests = array(
          new tree_eq(17, array('A','B')),
          new tree_eq(3, array('A','C')),
          new tree_eq('32' , array('U','W')), //'39' => array('S','U','W'),
          new tree_eq('29', array('Q','U','V')),
          new tree_eq('25', array('T','V','X')),
          new tree_eq('30' ,array('J','K','O')), //  '60' => array('I','J','K','N','O'), - I=6 - 24 N
          new tree_eq('29' ,array('F','H','K')), // '49' => array('F','H','K','M'),
          new tree_eq(55, array('O','R','T')), // '75' => array('M','O','R','T'),
          new tree_eq('48' , array('L','P','Q')),  //     '79' => array('L','N','P','Q','S'), - 7 (S) - 24 (N)
          new tree_eq('54' , array('E','G','J','L')));
          
          $apaths = array('BAC','BACF','DC','DCB','DCBA','DF','DFI','E','EG','EL','EJ');
          $ipaths = array('I', 'IJ','IK','IN','IM','IO','IKH','IKD','INR','INT');
          $this->constraints = array(
              new tree_constraint(8,array('H','HF','HK')),
              new tree_constraint(19,$apaths), //definitively here
              new tree_constraint(23,$apaths),
              new tree_constraint(6,$ipaths),
              new tree_constraint(9,$ipaths),
              new tree_constraint(16,$ipaths),
              new tree_constraint(31,array('GE','GED','GEC','GJ','GL'))
          );
              
     
          
          
            $this->vals = array ('A' => 2,'B' => 15, 'C'=> 1, 'D' => 12,
            'M' => 20, 'N'=> 24,'S' => 7,
            'I' => 6 // because 1 and 2 are taken making impossible for 2 items to equal 6    
            ); //B could be 15 or 16
            $go = range('A','X');
            $max_limit = array('H' => 8, 'F' => 21, 'B' => 16);
         //   $min_limit = array('O' => 10, 'R' => 10, 'T' => 10,'W' >= 10);
            $min_limit = array('B' => 15,'H' => 5);
          //  $three_poss = array('J' => 1,'K' => 1, 'O' => 1);
            
            foreach ($go as $var) {
    	       	$z = new walk_var($var,$this);
    	       	if (isset($this->vals[$var])) { 
    	       	    $z->set_static($this->vals[$var]);
    	       	}
    	       	if (isset($max_limit[$var])) {
    	       	    $z->clamp_upper_limit($max_limit[$var]);
    	       	}
    	//       	if (isset($three_poss)) { $z->lower_limit = 3; }
    	       	if (isset($min_limit[$var])) {
    	       	    $z->clamp_lower_limit($min_limit[$var]);
    	       	}
    	       	
    	       $this->vars[$var] = $z;
            }
           $this->populate_poss();
      }
    
    
      function populate_poss()
      {
        $this->poss = array_diff(range(1,24),$this->vals);
    	$this->poss = array_flip($this->poss);
      }
    
    
    
      function degem()
      {
          foreach ($this->vars as  $wvar)
           {
            if ($wvar->mode == 'gemini') {    $wvar->unset();  }
           }
      }
      
      function rescreen(string $x)
        {
            echo "\033[" . $this->reverse . "D";      // Move 5 characters backward
            echo $x;
            $this->reverse = strlen($x);
        }
    
       function set_var($var,$val)
       {
        	if (in_array($val,$this->vals)) {
                return false;
        	}
            $this->vals[$var] = intval($val);
            unset($this->poss[$val]);
            return true;
       }
       
       function unset_var($var,$value)
       {
          $this->poss[$value] = 1;
          unset($this->vals[$var]);
       }
    
       function assess_eq(array $wvars,$eq_num,$gap, $min_num = 3, $max_num = 23) 
        {
           $count = sizeof($wvars);
           if ($count == 1) {
    	      $wvars[0]->add_eq($eq_num,array($gap));
        	 return true;
    	   }
    	   $min =  min($this->poss);
    	   $max =  max($this->poss);
    	   if ($gap > ($max * $count)) {  $this->rescreen( 'gap of ' . $gap . ' exceeds possibility of ' . ($max * $count));   return false; } //fail if the number would require impossible high answers
           if (($min * $count) > $gap) {  $this->rescreen( 'possibility of ' . ($min * $count) . ' exceeds gap of' . $gap);  return false; } //fail if the available numbers are too big
    
    
    	if ($count == 2) {		//improved handling for two variable case
    			$poss2 = array();
    			foreach ($this->poss as $x => $y)
    			{
    				if (($gap - $x) == $x) { continue; }
    				if (isset($this->poss[$gap - $x])) {
    					$poss2[$gap - $x] = $x;
    					//echo 'was possible on ' . $x . ' and  ' . ($gap - $x) . "\n";
    				}
    			}
    			if (sizeof($poss2) == 0) {  
    			 $this->rescreen('fail due to no matching pair adding up to' . $gap);
    			 return false; 	
    			} //no matching pair
    			$wvars[0]->add_eq($eq_num,$poss2); $wvars[0]->add_cond($wvars[1]->name,$poss2);
                $wvars[1]->set_mode('gemini',array($wvars[0]),$gap);
    		//	$wvars[1]->add_eq($eq_num,$poss2); $wvars[1]->add_cond($wvars[0]->name,$poss2);
    		}
    
    		if ($count == 3) {
    			$poss3 = array();
    			$posses = array_keys($this->poss);
    			foreach ($posses as $x) {
    				foreach ($posses as $y) {
    				    if ($y == $x) { continue; }
    					foreach ($posses as $z) {
    						if ($z == $x) { continue; }
    						if ($z == $y) { continue; }
    						if ($gap == ($x + $y + $z)) { $poss3[] = $x;  }
    					}
    				}
    			}
    			$poss3 = array_flip(array_flip($poss3));
        		if (sizeof($poss3) == 0) {  
                     $this->rescreen('fail due to no matching 3-set adding up to' . $gap);
        			exit();
                    return false;  
                } 
                      
            $wvars[0]->add_eq($eq_num,$poss3);
            $wvars[1]->add_eq($eq_num,$poss3);
            $wvars[2]->add_eq($eq_num,$poss3);
            $wvars[2]->set_mode('gemini',array($wvars[0],$wvars[1]),$gap);
            
   
           
    		}
    		
    		if ($count >= 4)
    		{
    		    $poss4 = array();
    		    $posses = array_keys($this->poss);
    		    foreach ($posses as $x) {
    		        foreach ($posses as $y) {
    		            if ($y == $x) { continue; }
    		            foreach ($posses as $z) {
    		                if ($z == $x) { continue; }
    		                if ($z == $y) { continue; }
    		                foreach ($posses as $a) {
    		                    if ($z == $a) { continue; }
    		                    if ($y == $a) { continue; }
    		                    if ($x == $a) { continue; }
    		                    if ($gap == ($x + $y + $z + $a)) { $poss4[] = $x;  }
    		                }
    		            }
    		            }
    		     }
    		   
    		    $poss4 = array_flip(array_flip($poss4));

    		    if (sizeof($poss4) == 0) {
    		        $this->rescreen('fail due to no matching 4-set adding up to' . $gap);
    		        exit();
    		        return false;
    		    }
    		    
    		    $wvars[0]->add_eq($eq_num,$poss4);
    		    $wvars[1]->add_eq($eq_num,$poss4);
    		    $wvars[2]->add_eq($eq_num,$poss4);
    		    $wvars[3]->add_eq($eq_num,$poss4);
    		    $wvars[3]->set_mode('gemini',array($wvars[0],$wvars[1],$wvars[2]),$gap);
    		    
    		    

            }

            
            echo 'Equation ' . $eq_num . ' with gap ' . $gap . ' of ';
            //resolve max and min values
            $max_value = $gap;
            $min_value = $gap;
            /*$max_value = $gap - 12; // 23 + 3 + 4 + 5
            $min_value = $gap -66; // 23 + 22 + 21 = 66*/
            while ($count >1 ) {
                $count--;
                $max_value -= $min_num;
                $min_num++;
                $min_value -= $max_num;
                $max_num--;
            }
            echo ' between ' .  $min_value . ' and ' . $max_value .'  (min_avail = ' . $min_num . '; max_avail = ' . $max_num .  ")\n";
            
            
            foreach ($wvars as $wv) {
                $wv->clamp_upper_limit ($max_value);
                $wv->clamp_lower_limit ($min_value);
            }
            
            
            return true;
       
       }
        
        function running_time()
        {
            return  microtime(true) - $this->start_time;
        }
        
        function arrange($show = false,$filter= false)
        {
            $total_poss = 1;
                foreach ($this->vars as $wvar) {
                    if ($wvar->mode == 'static') { continue; }

                    if ($show) { //for the first run without a filter show the geminis
                        if ($wvar->mode == 'gemini') {   echo 'var ' . $wvar->name  . $wvar->display_cond()  .  "\n"; continue; }
                    } 
                    $var_poss = $wvar->assess($filter);

                    if ($var_poss == 0) {
                        return 0;
                    }
                    if ($var_poss == 1) {
                        if ($show) { echo 'var ' . $wvar->name . ' resolves to ' . $wvar->value . " (" . $wvar->mode . ")\n"; }
                    } else {
                        if ($show) { echo 'var ' . $wvar->name . ' did not resolve ('  .  $var_poss . ' possibilities / min of ' . $wvar->lower_limit . ' max of ' . $wvar->upper_limit  . ' )' . "\n"; }
                        $total_poss = $total_poss * $var_poss;
                    }
                }
                if ($show) { echo $total_poss . ' total possible arrangements(non-corrected) ' . "\n"; }
                return $total_poss;
        }
    
        function show_vals($poss_count = false)
        {
            $sort_order = range('A','X');
            $count= 0;
            $red = "\033[0;31m";
            $clear = "\033[0m";
            echo "\n Unassigned Values : " . implode(',',array_diff(array_keys($this->poss),$this->vals)) . "\n"; 
            echo "\n Variables: \n";
            foreach ($sort_order as $var) {
                $count++;
                echo '      ';
                if (isset($this->vals[$var])) { echo sprintf('%1$s=%2$02d', $var ,$this->vals[$var]); }
                else { echo $red . $var . '=  ' . $clear; }
                if (($count % 6) == 0) { echo "\n"; }
            }
            echo "\n";            
            if ($poss_count)  {
                echo 'Total possibilities from this run:  ' . $poss_count . "\n";
            }
        }
        
        function try_layer( $depth = 0, $possibilities = [])
        {
            if ($depth <= 6) {
                $possibilityStrings = [];
                foreach ($possibilities as $var => $pv) {
                    $possibilityStrings[] = "$var = $pv";
                }
                $rt = $this->running_time();
                $this->rescreen( sprintf('on run %2$u (%1$.3f) [%3$f tests/second] remained with ' . implode(' and ', $possibilityStrings) , $rt, $this->run ,    $this->run / $rt ));
            }
                     
            
            if ($depth == ($this->try_count)) {
                $poss_count  = $this->arrange(false, $possibilities);

                if ($poss_count == 0) {
                    $this->no_poss++;
                    $this->degem();
                    return false;
                }

                echo "\n passed all gemini with $poss_count \n";
                foreach ($this->constraints as $x)
                {
                    if (!$x->test($this)) {
                  //      echo "\n failed on constraint " . $x->target . "\n";
                        return false;
                    }
                }

                echo 'passed all constraints!!!';            
      
                    $this->best_poss = $poss_count;
                    $this->show_vals($poss_count);
                    exit();
                    
               $this->degem();
                
               return false;
            }
            
            $currentVar = $this->try_keys[$depth];
            foreach ($this->tries[$currentVar] as $currentValue) {
               // if ($currentVar =='E') { if ($currentValue == 3) { echo "skipping E-3 \n"; continue; } }
                $this->run++;
                if (!$this->vars[$currentVar]->set_poss($currentValue)) { continue; }
                $newPossibilities = $possibilities;
                $newPossibilities[$currentVar] = $currentValue;
                
                // Recursive call to the next depth level
                $this->try_layer($depth + 1, $newPossibilities);
                
                // Unset current variable possibility
                $this->vars[$currentVar]->unset();
            }
        }
        
        
        function try_wrapper()
        {
    
           $tries = array();
           foreach ($this->vars as $wvar)
           {
               if ($wvar->mode == 'static') { continue; }
               if ($wvar->mode == 'gemini') { continue; }
               $tries[$wvar->name] =  array_intersect($this->vars[$wvar->name]->poss,array_keys($this->poss));
               
           }
           $this->best_poss = 10000000000;
           $this->tries =  $tries;
           $this->try_keys =  array_keys($tries);
           $this->try_count = count($tries);
           
           echo 'layers of trying : ' . $this->try_count;
           $this->try_layer();
           print_r($this->vals);
           echo 'no poss outcomes: ' . $this->no_poss;
           exit();
        }
    
      
      function test()
      {
          $test_count = 0;
          $test_parts = array();
          foreach ($this->tests as $eq)
          {
              $test_count++;
              $hold_sum = 0;
              $test_parts[$test_count] = array();
              $wait_vars = array();
              foreach ($eq->vars as $var) {
                  if (isset($this->vals[$var])) {
                      $test_parts[$test_count][$var] = $this->vals[$var];
                      $hold_sum += $this->vals[$var];
                  } else { $wait_vars[] = $this->vars[$var];  }
                  
              }
              if  ((sizeof($wait_vars) > 0)) {
                  $gap = ($eq->sum - $hold_sum);
                  if (!$this->assess_eq($wait_vars,$test_count,$gap)) {
                      $this->rescreen('unsolvable at test #'  . $test_count . '  because of ' . $var . ' being unassignable ');
                      return false;
                  }
              }
          }
          
          $this->arrange(true);
          $this->try_wrapper();
          
      }
    
    }
    
    $x = new tree_tester();
    $x->test();
    
    
?>
