<?php

class A
{
    public function test()
    {
        $this->show();
    }

    private function show()
    {
        echo 1;
    }
}


class B extends A
{
    public function test()
    {
        $this->show();
    }

    private function show()
    {
        echo 2;
    }
}


$b = new B();
$b->test();