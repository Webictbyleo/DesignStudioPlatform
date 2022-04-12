# DesignStudioPlatform
Designrstudio PHP API Library

#PHP example
```php
require('{PATH_TO_PHP_LIBRARY}');  
$client = new DesignStudioPlatform\API('Your App ID','Your API Key');

//Batch process images  
//Created blurred image preview  
//Prepare files for upload
$files = $client->files(array(  
'{IMAGE SOURCE}',  
'{IMAGE SOURCE}'  
));
    try{  
$client->call('packages/create/blurimage',array(  
'images'=>$files,  
'callback'=>'{VALID URL HERE}'  
));
  }catch(Exception $err){
	
}
