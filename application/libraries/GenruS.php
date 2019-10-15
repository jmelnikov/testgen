<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Class GenruS
 * В данном варианте создаётся ещё одна таблица в полями id, is_loaded_big и is_loaded_small
 * В полях хранятся id изображение, а так же статус файлов (1 - найден, 0 - не найден) для больших и маленьких картинок соответственно
 * Каждый раз, перед вставкой новых данных, таблица очищается.
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
class GenruS
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

			// Здесь и далее предполагается, что папка с изображениями лежит в корне сайта и называется images
			$tmp['is_loaded_big'] = file_exists(FCPATH . 'images/' . $image->image_big) ? 1 : 0;
			$tmp['is_loaded_small'] = file_exists(FCPATH . 'images/' . $image->image_small) ? 1 : 0;

			if ($tmp['is_loaded_big'] == 1 && $tmp['is_loaded_small'] == 1) {
				continue;
			}

			$images_list[] = $tmp;
		}

		// Здесь и далее - очистка таблицы перед добавлением в неё списка не найденных файлов
		$this->ci->db->truncate('images_status');
		$this->ci->db->insert_batch('images_status', $images_list);
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
				continue;
			}

			$images_list[] = $tmp;
		}

		$this->ci->db->truncate('images_status');
		$this->ci->db->insert_batch('images_status', $images_list);
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
				continue;
			}

			$images_list[] = $tmp;
		}

		$this->ci->db->truncate('images_status');
		$this->ci->db->insert_batch('images_status', $images_list);
	}

	/**
	 * Метод перепроверяет наличие изображений, который ранее были помечены как незагруженные
	 */
	public function scan_only_missed_images()
	{
		$this->ci->db->select('images_status.id, product_id, image_small, image_big, is_loaded_small, is_loaded_big');
		$this->ci->db->from('images_status');
		$this->ci->db->join('images', 'images.id = images_status.id');
		$images = $this->ci->db->get();

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

			if ($tmp['is_loaded_big'] == 1 && $tmp['is_loaded_small'] == 1) {
				continue;
			}

			$images_list[] = $tmp;
		}

		$this->ci->db->truncate('images_status');
		$this->ci->db->insert_batch('images_status', $images_list);
	}

	/**
	 * @return int
	 * Метод возвращает количество незагруженных маленьких изображений
	 */
	public function get_missed_small_images_count(): int
	{
		$this->ci->db->where('is_loaded_small', 0);
		return $this->ci->db->count_all_results('images_status');
	}

	/**
	 * @return int
	 * Метод возвращает количество незагруженных больших изображений
	 */
	public function get_missed_big_images_count(): int
	{
		$this->ci->db->where('is_loaded_big', 0);
		return $this->ci->db->count_all_results('images_status');
	}

	/**
	 * @param bool $as_array
	 * @return mixed
	 * Метод возвращает объект (или массив, если параметром был передан true)
	 * с ID и расположением изображений
	 */
	public function get_missed_images_list(bool $as_array = false)
	{
		$this->ci->db->select('images_status.id, product_id, image_small, image_big, is_loaded_small, is_loaded_big');
		$this->ci->db->from('images_status');
		$this->ci->db->join('images', 'images.id = images_status.id');
		$images = $this->ci->db->get();

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
		$this->ci->db->select('images_status.id, image_small, is_loaded_small');
		$this->ci->db->where('is_loaded_small', 0);
		$this->ci->db->join('images', 'images.id = images_status.id');
		$images = $this->ci->db->get('images_status');

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
		$this->ci->db->select('images_status.id, image_big, is_loaded_big');
		$this->ci->db->where('is_loaded_big', 0);
		$this->ci->db->join('images', 'images.id = images_status.id');
		$images = $this->ci->db->get('images_status');

		if ($as_array) {
			return $images->result_array();
		} else {
			return $images;
		}
	}
}
