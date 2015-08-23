<?php

// uncomment the following to define a path alias
// Yii::setPathOfAlias('local','path/to/local-folder');

// This is the main Web application configuration. Any writable
// CWebApplication properties can be configured here.
return array(
	'preload'=>array('translate'),
	'basePath'=>dirname(__FILE__).DIRECTORY_SEPARATOR.'..',
	'name'=>'Future Mail',
	'theme'=>'manuscript',
	// preloading 'log' component
	'preload'=>array('log'),

	// autoloading model and component classes
	'import'=>array(
		'application.models.*',
		'application.components.*',
		'application.modules.translate.TranslateModule'
	),

	'modules'=>array(
		// uncomment the following to enable the Gii tool
		'translate',
		'gii'=>array(
			'class'=>'system.gii.GiiModule',
			'password'=>'futuremail',
		 	// If removed, Gii defaults to localhost only. Edit carefully to taste.
			'ipFilters'=>array('127.0.0.1','::1'),
		),
	),
	// application components
	'components'=>array(
		'user'=>array(
			// enable cookie-based authentication
			'allowAutoLogin'=>true,
		),
//define the class and its missingTranslation event
        'messages'=>array(
            'class'=>'CDbMessageSource',
            'onMissingTranslation' => array('TranslateModule', 'missingTranslation'),
			'sourceMessageTable'=>'sourcemessage',
			'translatedMessageTable'=>'message',
		),
        'translate'=>array(//if you name your component something else change TranslateModule
            'class'=>'translate.components.MPTranslate',
			'defaultLanguage'=>'de',
			//any avaliable options here
            'acceptedLanguages'=>array(
            	  'de'=>'Deutsch',
                  'en'=>'English',
				  'fr'=>'Français',
				  'it'=>'Italiano',
                  'pt'=>'Português'
                  
        	)
		),		
		// uncomment the following to enable URLs in path-format
		/*
		'urlManager'=>array(
			'urlFormat'=>'path',
			'rules'=>array(
				'<controller:\w+>/<id:\d+>'=>'<controller>/view',
				'<controller:\w+>/<action:\w+>/<id:\d+>'=>'<controller>/<action>',
				'<controller:\w+>/<action:\w+>'=>'<controller>/<action>',
			),
		),
		*/
		/*
		'db'=>array(
			'connectionString' => 'sqlite:'.dirname(__FILE__).'/../data/testdrive.db',
		),
		*/
		// uncomment the following to use a MySQL database
		
		'db'=>array(
			'connectionString' => 'mysql:host=localhost;dbname=futuremail',
			'emulatePrepare' => true,
			'username' => 'futuremail',
			'password' => 'futuremail',
			'charset' => 'utf8',
		),
		
		'errorHandler'=>array(
			// use 'site/error' action to display errors
            'errorAction'=>'site/error',
        ),
		'log'=>array(
			'class'=>'CLogRouter',
			'routes'=>array(
				array(
					'class'=>'CFileLogRoute',
					'levels'=>'error, warning',
				),
				// uncomment the following to show log messages on web pages
				/*
				array(
					'class'=>'CWebLogRoute',
				),
				*/
			),
		),
	),

	// application-level parameters that can be accessed
	// using Yii::app()->params['paramName']
	'params'=>array(
		// this is used in contact page
		'adminEmail'=>'zukunftemail@gmail.com',
	),
);