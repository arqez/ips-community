//<?php

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	exit;
}

class nexus_hook_DispatcherAdmin extends _HOOK_CLASS_
{

    public function finish()
	{
		if( $this->module->application == 'nexus' AND $this->module->key == 'hosting' )
		{
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global', 'core' )->message( 'nexus_hosting_deprecated', 'warning' ) . \IPS\Output::i()->output;
		}

		return parent::finish();
	}
}
