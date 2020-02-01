<?php

$computorv1 = new ComputorV1($argc, $argv);

class ComputorV1
{
	const DEF = "\033[0m";
	const RED = "\033[31m";
	const GREEN = "\033[32m";
	const YELLOW = "\033[33m";
	const BLUE  = "\033[34m";
	const PURPLE = "\033[35m";
	const AQUAMARINE = "\033[36m";
	const GREY = "\033[37m";

	public $equation;
	public $equation_parts;
	public $degree = 0;
	public $discriminant;

	public function __construct($argc, $argv)
	{
		$this->checkArguments($argc);
		$this->checkEquation($argv[1]);
		$this->solveEquation();
	}

	public function checkArguments($argc)
	{
		if ($argc < 2){
			$this->displayError("Only one argument passed. You need to set the equation.");
		} elseif ($argc > 2) {
			$this->displayError("To many arguments. You need to set the equation as second argument.");
		}
	}

	public function checkEquation($equation)
	{
		$this->equation = preg_replace('/\s+/', '', $equation);
		if (!preg_match("/^[0-9X*+\-\^.]+=[0-9X*+\-\^.]+$/", $this->equation)) {
			$this->displayError("The equation contains an invalid character or is not an equation.");
		}
		if (strripos($this->equation, 'X') === false) {
			$this->displayError("There are no variables in the equation.");
		}
		$this->checkCharactersDuplicate();
		$this->display("Initial equation: $this->equation", self::YELLOW);
		
	}

	public function checkCharactersDuplicate()
	{
		$duplicates = ['XX', '**', '^^', '+*', '-*'];
		foreach ($duplicates as $duplicate) {
			if (strpos($this->equation, $duplicate)) {
				$this->displayError("Characters duplicate: $duplicate");
			}
		}
	}

	public function solveEquation()
	{
		$this->checkDegree();
		$this->equationInOneSide();
		$this->getReducedForm();
		$this->removeBlankValues();
		$this->findEquationDegree();
		$this->chooseSolutionType();
	}

	public function checkDegree()
	{
		$equation = preg_replace('/[=|+|-]/', ' $0', $this->equation);
		$equation_parts = explode(' ', $equation);
		foreach ($equation_parts as $equation_part) {
			if (preg_match('/X\^(.*)/', $equation_part, $matches)) {
				if (!ctype_digit($matches[1])) {
					$this->displayError("Wrong degree of equation - $matches[0]");
				}
			}
		}
	}

	public function equationInOneSide()
	{
		$this->equation = preg_replace('/[+|-]/', ' $0', $this->equation);
		$equation_sides = explode('=', $this->equation);
		$equation_left_side = explode(' ', $equation_sides[0]);
		$equation_right_side = explode(' ', $equation_sides[1]);
		foreach ($equation_right_side as $key => $equation_part) {
			if ($equation_part[0] == '-') {
				$equation_right_side[$key][0] = '+';
			} elseif ($equation_part[0] == '+') {
				$equation_right_side[$key][0] = '-';
			} else {
				$equation_right_side[$key] = '-' . $equation_part;
			}
		}
		$this->equation_parts = array_merge($equation_left_side, $equation_right_side);
	}

	public function getReducedForm()
	{
		$this->equation = [];
		$this->getWithoutX();
		$this->getAllDegrees();
		if (!empty($this->equation_parts)) {
			$this->displayError("Wrong parts of equation - " . print_r($this->equation_parts, true));
		}
		$this->buildReducedForm();
	}

	public function getWithoutX()
	{
		$this->equation[0] = '';
		foreach ($this->equation_parts as $key => $equation_part) {
			if (!preg_match("/X/", $equation_part)) {
				$this->equation[0] = floatval($this->equation[0]) + floatval($equation_part);
				unset($this->equation_parts[$key]);
			}
		}
	}

	public function getAllDegrees()
	{
		foreach ($this->equation_parts as $key => $equation_part) {
			if (preg_match("/X\^(\d+)/", $equation_part, $matches)) {
				$value = preg_replace("/X\^(\d+)/", "", $equation_part);
				$value = str_replace('*', '', $value);
				if ($value == '-' || $value == '+' || $value === '') {
					$value = $value . '1';
				}
				if (isset($this->equation[$matches[1]])) {
					$this->equation[$matches[1]] = floatval($this->equation[$matches[1]]) + floatval($value);
				} else {
					$this->equation[$matches[1]] = floatval($value);
				}
				unset($this->equation_parts[$key]);
			}
		}
	}

	public function buildReducedForm()
	{
		$reduced_form = '';
		ksort($this->equation);
		foreach ($this->equation as $key => $value) {
			if ($reduced_form !== '' && floatval($value) >= 0) {
				$reduced_form .= ' + ';
			} elseif($reduced_form !== '' && floatval($value) < 0) {
				$reduced_form .= ' - ';
				$value = - $value;
			}
			if ($key == 0) {
				$reduced_form = $value;
			} else {
				$reduced_form .= "$value * X^$key";
			}
		}
		$this->display('Reduced form : ' . $reduced_form . ' = 0', self::AQUAMARINE);
	}

	public function removeBlankValues()
	{
		$reduced_form = '';
		foreach ($this->equation as $key => $value) {
			if (floatval($value) == 0) {
				if ($key != 0) {
					unset($this->equation[$key]);
				}
			} else {
				if ($reduced_form !== '' && floatval($value) > 0) {
					$reduced_form .= ' + ';
				} elseif($reduced_form !== '' &&  floatval($value) < 0) {
					$reduced_form .= ' - ';
					$value = - $value;
				}
				if ($key == 0) {
					$reduced_form = $value;
				} else {
					$reduced_form .= "$value * X^$key";
				}
			}
		}
		$this->display('Reduced form without blank values : ' . $reduced_form . ' = 0', self::PURPLE);
	}

	public function findEquationDegree()
	{
		$this->degree = 0;
		foreach ($this->equation as $key => $value) {
			if ($key > $this->degree) {
				$this->degree = $key;
			}
		}
		$this->display('Degree of equation is ' . $this->degree, self::BLUE);
	}

	public function chooseSolutionType()
	{
		if ($this->degree > 2) {
			$this->displayError('I can’t solve the equation, because the degree of the equation is > 2');
		}
		if ($this->degree == 0) {
			$this->checkEquality();
		} elseif ($this->degree == 1) {
			$this->solveLinearEquation();
		} else {
			$this->solveQuadraticEquation();
		}
	}

	public function checkEquality()
	{
		if ($this->equation[0] == 0) {
			$this->displayResult('Solution: X ∈ (-∞; +∞)');
		} else {
			$this->displayResult('Solution: X = ∅');
		}
	}

	public function solveLinearEquation()
	{
		if ($this->equation[0] != 0) {
			$this->equation[0] =  - $this->equation[0];
		}
		$this->display('Linear equation: ' . $this->equation[1] . 'X = ' . $this->equation[0], self::YELLOW);
		$result = $this->equation[0] / $this->equation[1]; 
		$this->displayResult('Solution: X = ' . $result);
	}

	public function solveQuadraticEquation()
	{
		$this->prepareEquationValues();
		$this->findDiscriminant();
		if ($this->discriminant == 0) {
			$this->zeroDiscriminant();
		} elseif ($this->discriminant > 0) {
			$this->positiveDiscriminant();
		} else {
			$this->negativeDiscriminant();
		}
	}

	public function prepareEquationValues()
	{
		if (array_key_exists(1, $this->equation) === false) {
			$this->equation[1] = 0;
		}
	}

	public function findDiscriminant()
	{
		$this->discriminant = $this->equation[1] * $this->equation[1] - 4 * $this->equation[2] * $this->equation[0];
		$this->display('Discriminant of the quadratic equation ax^2 + bx + c = 0: D = b^2 - 4ac'. PHP_EOL . 'D = ' . $this->discriminant , self::YELLOW);
	}

	public function zeroDiscriminant()
	{
		$this->display('Quadratic equation has only one solution X = -b / 2a', self::AQUAMARINE);
		$result = (-$this->equation[1]) / (2 * $this->equation[2]);
		$this->displayResult('Solution: X = ' . $result);
	}

	public function positiveDiscriminant()
	{
		$this->display('Discriminant is greater than 0, so quadratic equation has two solutions', self::AQUAMARINE);
		$this->display('X1,2 = (-b ± √D) / 2a', self::PURPLE);
		$square_root = sqrt($this->discriminant);
		$x1 = (-$this->equation[1] + $square_root) / (2 * $this->equation[2]);
		$x2 = (-$this->equation[1] - $square_root) / (2 * $this->equation[2]);
		$this->displayResult("Solutions: X1 = $x1, X2 = $x2");
	}

	public function negativeDiscriminant()
	{
		$this->display('Discriminant is less than 0, so quadratic equation has two irrational solutions', self::AQUAMARINE);
		$this->display('X1,2 = (-b ± √D) / 2a', self::PURPLE);
		$square_root = sqrt(-$this->discriminant) . 'i';
		$x1 = '(' . -$this->equation[1] .  " + $square_root) / " . 2 * $this->equation[2];
		$x2 = '(' . -$this->equation[1] .  " - $square_root) / " . 2 * $this->equation[2];
		$this->displayResult("Irrational solutions: X1 = $x1, X2 = $x2");
	}

	public function displayError($message)
	{
		exit(self::RED . $message . self::DEF . PHP_EOL);
	}

	public function display($message, $color = self::GREEN)
	{
		echo $color . $message . self::DEF . PHP_EOL;
	}

	public function displayResult($result)
	{
		$this->display($result);
		exit;
	}
}




















