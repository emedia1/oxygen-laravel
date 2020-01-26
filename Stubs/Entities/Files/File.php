<?php

namespace App\Entities\Files;

use Illuminate\Database\Eloquent\Model;
use EMedia\QuickData\Entities\Traits\RelationshipDataTrait;
use EMedia\QuickData\Entities\Search\SearchableTrait;
use Illuminate\Support\Facades\Storage;

class File extends Model
{

	use SearchableTrait; //, RelationshipDataTrait;

	protected $fillable = [
		'name',
		'key',
		'uuid',
		'description',
		'original_filename',
		'allow_public_access',
		'file_path',
		'file_disk',
		'file_url',
		'file_size_bytes',
		'uploaded_by_user_id',
	];

	protected $searchable = ['name', 'description', 'original_filename'];

	/**
	 *
	 * Lock file keys from being deleted
	 *
	 * @return array
	 */
	public static function getLockedFileKeys(): array
	{
		return [
			'privacy-policy',
			'terms-conditions',
		];
	}

	/**
	 *
	 * Pre-defined file keys
	 *
	 * @param null $key
	 *
	 * @return array|mixed
	 */
	public static function fileKeys($key = null)
	{
		$keys = [
			'privacy-policy' => 'Privacy Policy',
			'terms-conditions' => 'Terms & Conditions',
			'about-us' => 'About Us',
			'other' => 'Other',
		];

		if ($key && isset($keys[$key])) return $keys[$key];

		return $keys;
	}

	// use \Cviebrock\EloquentSluggable\Sluggable;

    /**
     * Return the sluggable configuration array for this model.
     *
     * @return array
     */
    /*
    public function sluggable()
    {
        return [
            'slug' => [
                'source' => 'name'
            ]
        ];
    }
    */


	/**
	 *
	 * Can users delete this file?
	 *
	 * @param $key
	 *
	 * @return bool
	 */
	public function isDeleteAllowed(): bool
	{
		if (in_array($this->key, self::getLockedFileKeys())) {
			return false;
		}

		if (in_array($this->custom_key, self::getLockedFileKeys())) {
			return false;
		}

		return true;
	}

	public function getPermalinkAttribute()
	{
		return route('files.show', [
			'uuid' => $this->uuid,
			'fileName' => $this->original_filename,
		]);
	}

	/**
	 *  Setup model event hooks
	 */
	public static function boot()
	{
		parent::boot();
		self::creating(function ($model) {
			$model->uuid = (string) \Webpatser\Uuid\Uuid::generate(4);
		});
	}

	public function getPublicUrlAttribute()
	{
		if ($this->file_disk) {
			if ($this->file_disk === 'public') {
				return Storage::disk('public')->url($this->file_path);
			}
		}

		return null;
	}

}