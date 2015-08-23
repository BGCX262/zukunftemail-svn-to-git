<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head>
    <title><?php echo CHtml::encode($this->pageTitle); ?></title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <link rel="stylesheet" type="text/css" href="<?php echo Yii::app()->theme->baseUrl; ?>/css/style.css" media="screen, projection" />
	<link rel="stylesheet" type="text/css" href="<?php echo Yii::app()->request->baseUrl; ?>/css/form.css" />
</head>
<body>
<div class="content">
  <div id="header">
    <div class="title">
    	<?php 
    	
    	$translate=Yii::app()->translate;
    	echo $translate->dropdown(); 
    	if($translate->hasMessages()){
    		//generates a to the page where you translate the missing translations found in this page
    		echo $translate->translateLink('Translate');
    		//or a dialog
    		echo $translate->translateDialogLink('Translate','Translate page title');
    	}
    	//link to the page where you edit the translations
    	echo $translate->editLink('Edit translations page'); echo " | ";
    	//link to the page where you check for all unstranslated messages of the system
    	echo $translate->missingLink('Missing translations page');
    	    	
    	
    	?>
      <h1>Future Mail</h1>
      <h3><?php echo Yii::t('app','slogan')?> </h3>
    </div>
  </div>
  <div id="main">
    <div class="center">
        <?php echo $content; ?>
    </div>
    <div class="leftmenu">
      <div class="nav">
		<?php $this->widget('zii.widgets.CMenu',array(
			'items'=>array(
				array('label'=>Yii::t('app','Home'), 'url'=>array('/site/index')),
				array('label'=>Yii::t('app','Login'), 'url'=>array('/site/login'), 'visible'=>Yii::app()->user->isGuest),
				array('label'=>Yii::t('app','Logout').' ('.Yii::app()->user->name.')', 'url'=>array('/site/logout'), 'visible'=>!Yii::app()->user->isGuest),
						
				array('label'=>Yii::t('app','Services'), 'url'=>array('services/index'), 'items'=>array(
					array('label'=>Yii::t('app','Message to an unborn person'), 'url'=>array('services/newmessage', 'tag'=>'newunborn')),
					array('label'=>Yii::t('app','Message to a person'), 'url'=>array('services/newmessage', 'tag'=>'new')),	
					array('label'=>Yii::t('app','Prices'), 'url'=>array('services/prices', 'tag'=>'prices')),
				)),				


				array('label'=>Yii::t('app','How it works'), 'url'=>array('/site/procedure')),
				
				array('label'=>Yii::t('app','Registration'), 'url'=>array('/site/registration')),
		
				array('label'=>Yii::t('app','User Reviews'), 'url'=>array('/site/userreviews')),
				array('label'=>Yii::t('app','Contact'), 'url'=>array('/site/contact')),
				array('label'=>Yii::t('app','About'), 'url'=>array('/site/page', 'view'=>'about')),
			),
		)); ?>
      </div>
    </div>
  </div>
  <div id="prefooter">
  	  <div class="center">
      	<h2><?php echo Yii::t('app','Create now a free account'); Yii::app()->translate->editLink('Translate');?></h2>
      </div>
  </div>
  <div id="footer">
    <div class="padding"> &copy; <?php echo date( 'Y', time() ) . ' ' . CHtml::encode( Yii::app()->name ); ?>    
    </div>
  </div>
</div>
</body>
</html>
