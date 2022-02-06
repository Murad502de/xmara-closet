<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
	use HasFactory;

	protected $table = 'leads_zum_schlissen';
	protected $fillable = [
		'lead_id',
	];


	public function get ( $id )
    {
        return $this->where( 'id_target_lead', $id )->first();
    }

    public function add () {}

    public function deleteLead ( $id )
	{
		return $this->where( 'lead_id', ( int ) $id )->delete();
	}

    public function aktualisieren () {}
}
