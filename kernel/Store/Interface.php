<?php

interface KalonQueStoreInterface
{
    public function addItem($item);

    public function deleteItem($itemId);
    
    public function read();
    
    public function isEmpty();
    
    public function pop();
}

?>