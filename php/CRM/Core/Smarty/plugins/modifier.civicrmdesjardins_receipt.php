<?php

function smarty_modifier_civicrmdesjardins_receipt($trx_id) {
  return db_query('SELECT receipt FROM {civicrmdesjardins_receipt} WHERE trx_id = :trx_id', array(':trx_id' => $trx_id))->fetchField();
}

