<?php

/**
 * Класс для работы с изображениями.
 *
 * image = new Images('pathtoimagefile.jpg');
 * image->resize(width, height);
 * image->putImg(img, pos, x, y);
 * image->rise();
 * image->nocrop([color]);
 * image->limitBothSides();
 * image->rounded([radius], [color]);
 * image->putText(text, font, [position], [x], [y], [color], [size], [angle], [shadow], [width], [height]);
 * image->save([name], [qua]);
 * image->display([format], [qua]);
 *
 * @package Utils
 * @author a.parkhomenko <771067@gmail.com>
 * @version v0.3
 * @since 20.11.2010
 * @copyright (c) Siberian Framework
 */
class Images
{
	protected $format; // Формат загруженного изображения
	protected $file; // Путь к файлу с исходным изображением
	protected $remote_file = false; // Метка, является ли изображение загруженным с удаленного сервера
	protected $dst; // Изображение в процессе обработки
	protected $params;

	public function init()
	{
	}


	/**
	 * Загрузка исходного изображения
	 *
	 * @param string $file Путь к исходному изображению
	 * @return void
	 */
	public function __construct($file = false, $remote = false)
	{
		$this->params = $this->_getDefaultParams();
		if ($file)
		{
			$this->load($file, $remote);
		}
	}


	public function getDst()
	{
		return $this->dst;
	}

	/**
	 * Проверяет существование и формат файла с изображением и загружает его в память.
	 *
	 * @param string $file Имя изображения (файл)
	 * @param bool $remote Флаг, разрешение грузить с удаленных серверов
	 * @param bool $main
	 * @return $this
	 */
	public function load($file, $remote = false, $main = true)
	{
		$work_file = '';

		// Проверка существования файла
		if (is_file($file))
		{
			$work_file = $file;
		}
		else
		{
			if ($remote)
			{
				$work_file = sys_get_temp_dir() . md5(microtime());
				@copy($file, $work_file);
				if ($main == true)
				{
					$this->remote_file = true;
				}
			}
		}

		if (filesize($work_file) == false) // Если файл не является изображением
		{
			return $this;
		}

		$imginfo = getImageSize($work_file); // Информация об изображении

		if ($imginfo[2] == 1)
		{
			$dst    = imageCreateFromGif($work_file);
			$format = 'gif';
		}
		elseif ($imginfo[2] == 2)
		{
			$dst    = imageCreateFromJpeg($work_file);
			$format = 'jpg';
		}
		elseif ($imginfo[2] == 3)
		{
			$dst = imageCreateFromPng($work_file);
			imagealphablending($dst, true);
			imagesavealpha($dst, true);
			$format = 'png';
		}
		else
		{
			throw new Exception('Неверный формат изображения (' . $imginfo['mime'] . '). Возможные форматы: jpeg, gif, png.');
		}

		if ($main)
		{
			$this->dst    = $dst;
			$this->format = $format;
			$this->file   = $work_file;
			return $this;
		}
		else
		{
			return $dst;
		}
	}


	/**
	 * Изменение размера изображения. Подготовка
	 * Проверка данных об изменении размера изображения. Запуск метода реализующего изменение размера изображения
	 *
	 * @param int $width Ширина изображения
	 * @param int $height Высота изображения
	 * @return $this
	 */
	public function resize($width = 0, $height = 0)
	{
		$this->params['width']  = intval($width);
		$this->params['height'] = intval($height);
		return $this;
	}

	/**
	 * Запретить кроп изображения
	 *
	 * @param string $color Цвет фона
	 * @return $this
	 */
	public function nocrop($color = '000')
	{
		$this->params['crop']    = false;
		$this->params['bgcolor'] = $color;
		return $this;
	}


	/**
	 * Возможность увеличивать размеры исходного изображения
	 *
	 * @return $this
	 */
	public function rise($val = true)
	{
		$this->params['rise'] = $val;
		return $this;
	}


	/**
	 * Ограничить размеры по обеим сторонам ($this->params['width'])
	 *
	 * @return $this
	 */
	public function limitBothSides()
	{
		$this->params['limit_both_sides'] = true;
		return $this;
	}


	/**
	 * Параметры по-умолчанию
	 *
	 * @return array
	 */
	private function _getDefaultParams()
	{
		return array(
			'width'            => 0,
			'height'           => 0,
			'crop'             => true,
			'bgcolor'          => false,
			'rise'             => false,
			'limit_both_sides' => false,
			'rounded'          => false,
			'rounded_color'    => false,
			'putImage'         => array(),
			'putText'          => array(),
			'rounded_radius'   => 0
		);
	}


	/**
	 * Изменение изображения.
	 *
	 */
	public function toModify()
	{
		$this->_resizeExec();
		$this->_putImagesExec();
		$this->_roundedExec();
		$this->_putTextExec();
		return $this;
	}


	private function _resizeExec()
	{
		if ($this->params['width'] == 0 && $this->params['height'] == 0)
		{
			return false;
		}
		$dstW             = $this->params['width'];
		$dstH             = $this->params['height'];
		$crop             = $this->params['crop'];
		$bgcolor          = $this->params['bgcolor'];
		$rise             = $this->params['rise'];
		$limit_both_sides = $this->params['limit_both_sides'];

		$srcW = $this->width(); // Ширина исходного изображения
		$srcH = $this->height(); // Высота исходного изображения

		/*
		 * Если не разрешено изменять размер изображения в большую сторону от исходного
		 * Приравниваем новые значения исходным
		 * По-умолчанию не разрешено.
		 */
		if ($rise == false && ($dstW != $dstH || $crop))
		{
			if ($srcW < $dstW)
			{
				$dstW = $srcW;
			}
			if ($srcH < $dstH)
			{
				$dstH = $srcH;
			}
		}

		//echo $dstW;echo '-';echo $dstH;exit;


		// Начальные размеры сдвигов изображений
		$dstX = $dstY = $srcX = $srcY = 0;

		// Если задан параметр limit_both_sides
		if ($limit_both_sides == true)
		{
			$dstH = $dstW;
			if ($srcW > $srcH)
			{
				if ($dstW > $srcW && $rise == false)
				{
					$dstW = $srcW;
				}
				$dstH = round($dstW * $srcH / $srcW);
			}
			else
			{
				if ($dstW > $srcH && $rise == false)
				{
					$dstH = $srcH;
				}
				$dstW = round($dstH * $srcW / $srcH);
			}
		}

		/*
		 * Если один из размеров изображения имеет нулевое значение:
		 * 1 отменяем разрешение обрезать изображение;
		 * 2 определяем его соответствующим размером исходного изображения (пропорционально).
		 */
		if ($dstW == 0 || $dstH == 0)
		{
			$crop = false;
			if ($dstW == 0)
			{
				$dstW = round($dstH * $srcW / $srcH);
			}
			if ($dstH == 0)
			{
				$dstH = round($dstW * $srcH / $srcW);
			}
		}

		$src = imageCreateTrueColor($dstW, $dstH); // Создаем холст для нового изображения

		if ($bgcolor) // Если задан цвет фона
		{
			$color = $this->_getColor($bgcolor);
			imageFilledRectangle($src, 0, 0, $dstW, $dstH, imageColorAllocate($src, $color[0], $color[1], $color[2]));
		}

		if ($dstW / $dstH < $srcW / $srcH) // Если соотношения сторон исходного изображения меньше итогового
		{
			if ($crop)
			{
				$srcW = round($srcH * $dstW / $dstH);
				$srcX = (imageSX($this->dst) - $srcW) / 2;
			}

			else
			{
				$z    = $dstH;
				$dstH = round($dstW * $srcH / $srcW);
				$dstY = ($z - $dstH) / 2;
			}
		}

		else // Если соотношения сторон исходного изображения больше или равно итоговому
		{
			if ($crop)
			{
				$srcH = round($srcW / $dstW * $dstH);
				$srcY = (imageSY($this->dst) - $srcH) / 2;
			}
			else
			{
				$z    = $dstW;
				$dstW = round($dstH * $srcW / $srcH);
				$dstX = ($z - $dstW) / 2;
			}
		}

		// Копируем в холст исходное изображение с новыми размерами
		imageCopyResampled($src, $this->dst, $dstX, $dstY, $srcX, $srcY, $dstW, $dstH, $srcW, $srcH);
		$this->dst = $src;
	}


	/**
	 * Возвращает ширину изображения
	 *
	 * @return int
	 */
	public function width()
	{
		return imageSX($this->dst);
	}


	/**
	 * Возвращает высоту изображения
	 *
	 * @return int
	 */
	public function height()
	{
		return imageSY($this->dst);
	}


	/**
	 * Вывод изображения на экран
	 *
	 * @param string $format Формат выходного файла
	 * @param int $qua Качество изображения (для JPEG)
	 * @return $this
	 */
	public function display($format = true, $qua = 90)
	{
		$this->_printExec(null, $format, $qua);
		return $this;
	}


	/**
	 * Сохранение изображения
	 *
	 * @param string $file Имя выходного файла
	 * @param string $format Формат выходного файла
	 * @param int $qua Качество изображения (для JPEG)
	 * @return $this
	 */
	public function save($file = '', $qua = 90)
	{
		// Имя файла
		$file = $file && is_string($file) ? $file : $this->file;

		$format = substr($file, strrpos($file, '.') + 1);
		$this->_printExec($file, $format, $qua);
		return $this;
	}


	/**
	 * Выполнение вывода или сохранения изображения
	 *
	 * @param string $file Имя выходного файла
	 * @param string $format Формат выходного файла
	 * @param int $qua Качество изображения (для JPEG)
	 * @return bool
	 */
	private function _printExec($file, $format, $qua)
	{
		if ($this->dst == false)
		{
			return false;
		}
		$this->toModify();

		if (is_bool($format) || is_null($format))
		{
			$format = $this->format;
		}
		$format = strtolower($format);

		// Формат выходного файла
		if ($format != 'jpeg' && $format != 'jpg' && $format != 'gif' && $format != 'png')
		{
			throw new Exception('Неправильный формат выходного изображения. Возможные значения: jpeg, gif, png.');
		}

		// Если вывод файла
		if ($file == null)
		{
			header('Content-type: image/' . $format);
		}

		else // Если сохранение файла
		{
			$pathA = pathinfo($file);

			// Если нет папки, пытаемся создать
			$this->createDir($pathA['dirname'], 0775);

			if ($this->_writeableFile($file) == false)
			{
				throw new Exception('Нет прав на запись для файла: ' . getcwd() . '/' . $file);
			}
		}

		$q = ($qua > 0 && $qua <= 100) ? $qua : true;

		switch ($format) // Определение типа изображения
		{
			case 'gif' :
				return imageGif($this->dst, $file); // Тип изображения Gif
			case 'png' :
				return imagePng($this->dst, $file); // Тип изображения Png
			case 'jpeg':
			case 'jpg':
				return imageJpeg($this->dst, $file, $q); // Тип изображения Jpeg
		}
	}


	/**
	 * Наложение дополнительного изображения на исходное
	 *
	 * Смещение изображения происходит с помощью параметров $x и $y
	 * Задаются в пикселях или процентах (%)
	 *
	 * @param string $file Имя накладываемого изображения
	 * @param int $pos Позиция изображения (начиная с 1 (правый верхний угол) против часовой стрелки)
	 * @param string $x Смещение накладываемого изображения по горизонтали
	 * @param string $y Смещение накладываемого изображения по вертикали
	 * @return bool
	 */
	public function putImage($file, $position = 'left top', $x = 0, $y = 0, $alpha = 100)
	{
		$this->params['putImage'][] = array(
			'file'     => $file,
			'position' => $position,
			'x'        => $x,
			'y'        => $y,
			'alpha'    => $alpha
		);
		return $this;
	}


	private function _putImagesExec()
	{
		if ($this->params['putImage'])
		{
			foreach ($this->params['putImage'] as $params)
			{
				// Загрузка нового изображения
				$src = $this->load($params['file'], true, false);

				// Размеры нового изображения
				$w = imageSX($src);
				$h = imageSY($src);

				// Получить координаты исходного изображения, от которого будет накладываться изображение
				$position = $this->_getPosition($params['position'], $params['x'], $params['y'], $w, $h);

				// Ноложение нового изображения на исходное
				if ($params['alpha'] == 100)
				{
					imagecopy($this->dst, $src, $position[0], $position[1], 0, 0, $w, $h);
				}
				else
				{
					imagecopymerge($this->dst, $src, $position[0], $position[1], 0, 0, $w, $h, $params['alpha']);
				}
			}
			$this->params['putImage'] = array();
		}
	}


	/**
	 * Наложение текста на исходное изображение
	 *
	 * @param string $text Текст
	 * @param string $font Путь к файлу со шрифтом
	 * @param int $position Позиция элемента
	 * @param string $x Смещение накладываемого текста по горизонтал
	 * @param string $y Смещение накладываемого текста по вертикали
	 * @param string $color Цвет текста
	 * @param int $size Размер шрифта
	 * @param int $angle Угол наклона текста
	 * @param int $alpha Степень прозраности текста (от 0 до 127),
	 * @param int $width Ширина прямоугольника, в который должен поместитья текст (если размер шрифта равен нулю)
	 * @param int $height Высота прямоугольника, в который должен поместитья текст (если размер шрифта равен нулю)
	 * @return $this
	 */
	public function putText($text = '', $font, $position = 0, $x = 0, $y = 0, $color = '0', $size = 10, $angle = 0, $alpha = 0, $width = 0, $height = 0)
	{
		$this->params['putText'][] = array(
			$text, $font, $position, $x, $y, $color,
			intval($size), intval($angle), intval($alpha),
			intval($width), intval($height)
		);
		return $this;
	}


	/**
	 * Выполнение наложения текста
	 *
	 * @return null
	 */
	private function _putTextExec()
	{
		if ($this->params['putText'])
		{
			foreach ($this->params['putText'] as $text)
			{
				list($text, $font, $position, $x, $y, $color, $size, $angle, $alpha, $width, $height) = $text;

				// Проверяем шрифт на работоспособность
				if (@imageTtfBBox($size, 0, $font, $text) == false)
				{
					return false;
				}

				// Если размер шрифта задан не целым числом, используем максимально возможный размер
				if ($size == 0)
				{
					$width  = ($width > 0) ? $width : $this->width();
					$height = ($height > 0) ? $height : $this->height();
					$size   = $this->imageTtfGetMaxSize($angle, $font, $text, $width, $height);
				}

				// Получаем список цветов, разбитых на каналы [RGB]
				$clr = $this->_getColor($color);

				// Цвет текста
				$color = imageColorAllocateAlpha($this->dst, $clr[0], $clr[1], $clr[2], $alpha);

				// Размер и положение текста
				$sz = $this->_imageTtfSize($size, $angle, $font, $text);

				// Координаты текста
				$coord = $this->_getPosition($position, $x, $y, $sz[0], $sz[1]);
				$coord[0] += $sz[2];
				$coord[1] += $sz[3];

				imageTtfText($this->dst, $size, $angle, $coord[0], $coord[1], $color, $font, $text);
			}
			$this->params['putText'] = array();
		}
	}


	/**
	 * Получить координаты накладываемого элемента
	 *
	 * @param int $position Позиция элемента
	 * @param string $x Смещение накладываемого элемента по горизонтал
	 * @param string $y Смещение накладываемого элемента по вертикали
	 * @param string $w Ширина накладываемого элемента
	 * @param string $h Высота накладываемого элемента
	 * @return list
	 */
	private function _getPosition($position, $x, $y, $w, $h)
	{
		$x1 = $this->width();
		$y1 = $this->height();

		// Определение параметров смещения. если указано в процентах
		if (substr($x, -1) == '%')
		{
			$x = $x1 * intval($x) / 100;
		}
		if (substr($y, -1) == '%')
		{
			$y = $y1 * intval($y) / 100;
		}
		$x = intval($x);
		$y = intval($y);

		switch ($position)
		{
			case 'right top':
				$x += $x1 - $w;
				break;
			case 'center top':
				$x += $x1 / 2 - $w / 2;
				break;

			case 'left center':
				$y += $y1 / 2 - $h / 2;
				break;

			case 'left bottom':
				$y += $y1 - $h;
				break;
			case 'center bottom':
				$x += $x1 / 2 - $w / 2;
				$y += $y1 - $h;
				break;

			case 'right bottom':
				$x += $x1 - $w;
				$y += $y1 - $h;
				break;
			case 'right center':
				$x += $x1 - $w;
				$y += $y1 / 2 - $h / 2;
				break;

			case 'center center':
				$x += $x1 / 2 - $w / 2;
				$y += $y1 / 2 - $h / 2;
				break;
		}

		return array(round($x), round($y));
	}


	/**
	 * Возвращает список цветов в десятиричном виде [RGB]
	 *
	 * @param string $color Цвет в 16-ричном формате, либо HTML-код
	 * @return list
	 */
	private function _getColor($color = '#fff')
	{
		if (!is_string($color))
		{
			$color = '#fff';
		}
		$color = str_replace('#', '', $color);

		// Если код цвета имеет 3 символа (короткая запись)
		if (strlen($color) == 3)
		{
			for ($i = 0; $i < 3; $i++)
				$c[] = hexdec(str_repeat(substr($color, ($i), 1), 2));
		}
		else
		{
			for ($i = 0; $i < 3; $i++)
				$c[] = hexdec(substr($color, ($i * 2), 2));
		}
		return $c;
	}


	/**
	 * Проверка файла, на возможность записи в него.
	 *
	 * @param string $file Файл, для записи
	 * @return bool
	 */
	private function _writeableFile($file)
	{
		return (is_file($file) && is_writeable($file) == false) ? false : true;
	}


	/**
	 * Вычисляет размеры прямоугольника с горизонтальными и вертикальными сторонами,
	 * в который вписан указанный текст.
	 * Результирующий массив имеет структуру:
	 * array(
	 *   0  => ширина прямоугольника,
	 *   1  => высота прямоугольника,
	 *   2  => смещение начальной точки по X относительно левого верхнего угла прямоугольника,
	 *   3  => смещение начальной точки по Y
	 * )
	 *
	 * @param int $size Размер шрифта
	 * @param int $angle Угол наклона текста
	 * @param int $font Путь к файлу со шрифтом
	 * @param string $text Текст
	 * @return list
	 */
	private function _imageTtfSize($size, $angle, $font, $text)
	{
		// Вычисляем охватывающий многоугольник
		// Вычисляем размер при НУЛЕВОМ угле поворота
		$horiz = imageTtfBBox($size, 0, $font, $text);

		// Вычисляим синус и косинус угла поворота
		$cos = cos(deg2rad($angle));
		$sin = sin(deg2rad($angle));
		$box = array();

		// Выполняем поворот каждой координаты
		for ($i = 0; $i < 7; $i += 2)
		{
			list ($x, $y) = array($horiz[$i], $horiz[$i + 1]);
			$box[$i]     = round($x * $cos + $y * $sin);
			$box[$i + 1] = round($y * $cos - $x * $sin);
		}

		$x = array($box[0], $box[2], $box[4], $box[6]);
		$y = array($box[1], $box[3], $box[5], $box[7]);

		// Вычисляем ширину, высоту и смещение начальной точки
		$width  = max($x) - min($x);
		$height = max($y) - min($y);
		return array($width, $height, 0 - min($x), 0 - min($y));
	}


	/**
	 * Метод возвращает наибольший размер шрифта, учитывая,
	 * что текст $text обязательно должен поместиться в исходное изображение
	 *
	 * @param int $angle Угол наклона текста
	 * @param int $font Путь к файлу со шрифтом
	 * @param string $text Текст
	 * @param int $width Ширина исходного изображения
	 * @param int $height Высота исходного изображения
	 * @return int
	 */
	public function imageTtfGetMaxSize($angle, $font, $text, $width, $height)
	{
		$min = 1;
		$max = $height;
		while (true)
		{
			// Рабочий размер - среднее между максимумом и минимумом.
			$size = round(($max + $min) / 2);
			$sz   = $this->_imageTtfSize($size, $angle, $font, $text);
			if ($sz[0] > $width || $sz[1] > $height)
			{
				// Будем уменьшать максимальную ширину до те пор, пока текст не
				// "перехлестнет" многоугольник.
				$max = $size;
			}
			else
			{
				// Наоборот, будем увеличивать минимальную, пока текст помещается.
				$min = $size;
			}
			// Минимум и максимум сошлись друг к другу.
			if (abs($max - $min) < 2)
			{
				break;
			}
		}
		return $min;
	}


	/**
	 * Очистка до исходного изображения
	 *
	 * Повторно загружает исходное изображение в память
	 * Отменяет все действия по изменению исходного изображения:
	 * размеры, наложение другого изображения, текста и т.п.
	 * Необходимо при сохранении нескольких изображений из исходного
	 * @param void
	 * @return void
	 */
	public function clean()
	{
		$this->load($this->file, true); // Загрузка изображения в память
		$this->params = $this->_getDefaultParams();
		return $this;
	}


	/**
	 * Освобождает память от используемых ресурсов и удаляет временный файл
	 *
	 * @param void
	 * @return void
	 *
	 */
	public function __destruct()
	{
		if ($this->remote_file && is_file($this->file))
		{
			unlink($this->file);
		}
		if (is_resource($this->dst))
		{
			imageDestroy($this->dst);
		}
	}


	/**
	 * Скругление углов
	 *
	 * @param int $radius
	 * @param bool $color
	 * @return $this
	 */
	public function rounded($radius = 0, $color = false)
	{
		$this->params['rounded']        = true;
		$this->params['rounded_radius'] = $radius;
		$this->params['rounded_color']  = $color;
		return $this;
	}


	/**
	 * Выполнение скругления углов
	 * @return bool
	 */
	private function _roundedExec()
	{
		if ($this->params['rounded'] == false)
		{
			return false;
		}
		$radius = $this->params['rounded_radius'];
		$color  = $this->params['rounded_color'];

		/**
		 * Чем выше rate, тем лучше качество сглаживания и больше время обработки и
		 * потребление памяти.
		 *
		 * Оптимальный rate подбирается в зависимости от радиуса.
		 */
		$rate = 10;

		imagealphablending($this->dst, false);
		imagesavealpha($this->dst, true);

		$width  = $this->width();
		$height = $this->height();

		if ($radius == 0)
		{
			$radius = min($width, $height) / 2;
		}

		$rs_radius = $radius * $rate;
		$rs_size   = $rs_radius * 2;

		$corner = imagecreatetruecolor($rs_size, $rs_size);
		imagealphablending($corner, false);

		if ($color == false)
		{
			$trans = imagecolorallocatealpha($corner, 255, 255, 255, 127);
		}
		else
		{
			$aColor = $this->_getColor($color);
			$trans  = imagecolorallocatealpha($corner, $aColor[0], $aColor[1], $aColor[2], 0);
		}

		imagefill($corner, 0, 0, $trans);

		$positions = array(
			array(0, 0, 0, 0),
			array($rs_radius, 0, $width - $radius, 0),
			array($rs_radius, $rs_radius, $width - $radius, $height - $radius),
			array(0, $rs_radius, 0, $height - $radius),
		);

		foreach ($positions as $pos)
		{
			imagecopyresampled($corner, $this->dst, $pos[0], $pos[1], $pos[2], $pos[3], $rs_radius, $rs_radius, $radius, $radius);
		}

		$i   = -$rs_radius;
		$y2  = -$i;
		$r_2 = $rs_radius * $rs_radius;

		for (; $i <= $y2; $i++)
		{
			$y = $i;
			$x = sqrt($r_2 - $y * $y);
			$y += $rs_radius;
			$x += $rs_radius;
			imageline($corner, $x, $y, $rs_size, $y, $trans);
			imageline($corner, 0, $y, $rs_size - $x, $y, $trans);
		}
		foreach ($positions as $pos)
		{
			imagecopyresampled($this->dst, $corner, $pos[2], $pos[3], $pos[0], $pos[1], $radius, $radius, $rs_radius, $rs_radius);
		}
	}


	/**
	 * Создание директории с нужными правами
	 * @param string $opath Путь
	 * @param int $mode права доступа
	 * @return bool
	 */
	public function createDir($opath, $mode = 0777)
	{
		$path = '';

		if (substr($opath, 0, 1) == '/')
		{
			$path  = '/';
			$opath = substr($opath, 1);
		}

		$aPath = explode('/', preg_replace('#/$#', '', $opath));

		for ($i = 0; $i < count($aPath); $i++)
		{
			$path .= $aPath[$i] . '/';

			// Не используем рекурсивное создание директории для того, чтобы нужные нам права выставить только для вновь
			// созданных наддиректорий. То есть, например, чтобы корню сайта (/) не пытаться поменять права доступа.
			if (is_dir($path) == false)
			{
				if (mkdir($path, $mode))
				{
					chmod($path, $mode);
				}
				else
				{
					return false;
				}
			}
		}
		return is_dir($opath) ? true : false;
	}
}