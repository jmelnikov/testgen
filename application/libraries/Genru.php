<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Class Genru
 * В данном варианте в таблице images добавлены два поля: is_loaded_big и is_loaded_small
 * в которых хранятся результаты проверки (1 - файл найден, 0 - файл не найден).
 *
 * Методы:
 * scan_all_images - проверяет всю таблицу картинок на наличие их в файловой системе
 * scan_all_big_images - проверяет только большие картинки
 * scan_all_small_images - проверяет только маленькие картинки
 * scan_only_missed_images - проверяет только те картинки, которые ранее были помечены как не загруженные
 * get_missed_small_images_count - возвращает количество маленьких картинок, помеченных как не загруженные
 * get_missed_big_images_count - возвращает количество больших картинок, помеченных как не загруженные
 * get_missed_images_list - возвращает объект или массив с не загруженными картинками
 * get_missed_small_images - возвращает объект или массив с не загруженными маленькими картинками
 * get_missed_big_images - возвращает объект или массив с не загруженными большими картинками
 */
class Genru
{
	protected $ci;

	public function __construct()
	{
		// Получаем instance приложения
		$this->ci = &get_instance();
		// Подключаемся к БД
		// Этот шаг не требуется, если в настройках CI установлена автозагрузка библиотеки database
		$this->ci->load->database();
	}

	/**
	 * Метод сканирует все изображения, указанные в БД
	 */
	public function scan_all_images()
	{
		$this->ci->db->select('id, image_big, image_small');
		$images = $this->ci->db->get('images');

		$images_list = [];
		foreach ($images->result() as $image) {
			$tmp['id'] = $image->id;
			$tmp['is_loaded_big'] = 0;
			$tmp['is_loaded_small'] = 0;
			// Здесь и далее предполагается, что папка с изображениями лежит в корне сайта и называется images
			if (file_exists(FCPATH . 'images/' . $image->image_big)) {
				$tmp['is_loaded_big'] = 1;
			}

			if (file_exists(FCPATH . 'images/' . $image->image_small)) {
				$tmp['is_loaded_small'] = 1;
			}

			$images_list[] = $tmp;
		}

		$this->ci->db->update_batch('images', $images_list, 'id');
	}

	/**
	 * Метод проверяет наличие только больших изображений
	 */
	public function scan_all_big_images()
	{
		$this->ci->db->select('id, image_big');
		$images = $this->ci->db->get('images');

		$images_list = [];
		foreach ($images->result() as $image) {
			$tmp['id'] = $image->id;
			$tmp['is_loaded_big'] = 0;

			if (file_exists(FCPATH . 'images/' . $image->image_big)) {
				$tmp['is_loaded_big'] = 1;
			}

			$images_list[] = $tmp;
		}

		$this->ci->db->update_batch('images', $images_list, 'id');
	}

	/**
	 * Метод проверяет наличие только маленьких изображений
	 */
	public function scan_all_small_images()
	{
		$this->ci->db->select('id, image_small');
		$images = $this->ci->db->get('images');

		$images_list = [];
		foreach ($images->result() as $image) {
			$tmp['id'] = $image->id;
			$tmp['is_loaded_small'] = 0;

			if (file_exists(FCPATH . 'images/' . $image->image_small)) {
				$tmp['is_loaded_small'] = 1;
			}

			$images_list[] = $tmp;
		}

		$this->ci->db->update_batch('images', $images_list, 'id');
	}

	/**
	 * Метод перепроверяет наличие изображений, который ранее были помечены как незагруженные
	 */
	public function scan_only_missed_images()
	{
		$this->ci->db->select('id, image_big, image_small, is_loaded_big, is_loaded_small');
		$this->ci->db->or_where(['is_loaded_big' => 0, 'is_loaded_small' => 0]);
		$images = $this->ci->db->get('images');

		$images_list = [];
		foreach ($images->result() as $image) {
			$tmp['id'] = (int)$image->id;
			$tmp['is_loaded_big'] = $image->is_loaded_big;
			$tmp['is_loaded_small'] = $image->is_loaded_small;
			// Если image_big помечено как не загруженное, проверяем его наличие
			// Если файл найден, то ставим пометку о его наличии
			if ($image->is_loaded_big == 0) {
				$tmp['is_loaded_big'] = file_exists(FCPATH . 'images/' . $image->image_big) ? 1 : 0;
			}
			// Если image_small помечено как не загруженное, и проверяем его наличие
			// Если файл найден, то ставим пометку о его наличии
			if ($image->is_loaded_small == 0) {
				$tmp['is_loaded_small'] = file_exists(FCPATH . 'images/' . $image->image_small) ? 1 : 0;
			}

			$images_list[] = $tmp;
		}

		$this->ci->db->update_batch('images', $images_list, 'id');
	}

	/**
	 * @return int
	 * Метод возвращает количество незагруженных маленьких изображений
	 */
	public function get_missed_small_images_count(): int
	{
		$this->ci->db->where('is_loaded_small', 0);
		return $this->ci->db->count_all_results('images');
	}

	/**
	 * @return int
	 * Метод возвращает количество незагруженных больших изображений
	 */
	public function get_missed_big_images_count(): int
	{
		$this->ci->db->where('is_loaded_big', 0);
		return $this->ci->db->count_all_results('images');
	}

	/**
	 * @param bool $as_array
	 * @return mixed
	 * Метод возвращает объект (или массив, если параметром был передан true)
	 * с ID и расположением изображений
	 */
	public function get_missed_images_list(bool $as_array = false)
	{
		$this->ci->db->select('id, image_small, image_big, is_loaded_small, is_loaded_big');
		$this->ci->db->or_where(['is_loaded_big' => 0, 'is_loaded_small' => 0]);
		$images = $this->ci->db->get('images');

		if ($as_array) {
			return $images->result_array();
		} else {
			return $images;
		}
	}

	/**
	 * @param bool $as_array
	 * @return mixed
	 * Метод возвращает объект (или массив, если параметром был передан true)
	 * с ID и расположением маленького изображения
	 */
	public function get_missed_small_images(bool $as_array = false)
	{
		$this->ci->db->select('id, image_small');
		$this->ci->db->where('is_loaded_small', 0);
		$images = $this->ci->db->get('images');

		if ($as_array) {
			return $images->result_array();
		} else {
			return $images;
		}
	}

	/**
	 * @param bool $as_array
	 * @return mixed
	 * Метод возвращает объект (или массив, если параметром был передан true)
	 * с ID и расположением большого изображения
	 */
	public function get_missed_big_images(bool $as_array = false)
	{
		$this->ci->db->select('id, image_big');
		$this->ci->db->where('is_loaded_big', 0);
		$images = $this->ci->db->get('images');

		if ($as_array) {
			return $images->result_array();
		} else {
			return $images;
		}
	}
}
