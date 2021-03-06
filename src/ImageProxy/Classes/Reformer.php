<?php

namespace ImageProxy\Classes;

use SplFileInfo;

class Reformer
{
    private $proxy;

    public function __construct()
    {

        $this->siteUrl = apply_filters('ImageProxy__site-host', false);

        $this->proxy = new Builder();

    }

    public function init()
    {

        if (!$this->excludeAdminAjaxActions()) {
            return false;
        }

        if (apply_filters('ImageProxy__image-convert-disable', false)) {
            return false;
        }

        if (is_admin()) {
            return false;
        }

        if (is_blog_admin()) {
            return false;
        }

        $this->disableGenerateImageFiles();

        $this->replaceInTextContent();

        $this->replaceInCode();

        $this->replaceAvatars();
    }

    public function disableGenerateImageFiles()
    {
        add_filter('intermediate_image_sizes_advanced', [$this, 'disableGenerateThumbnails'], 20, 1);
    }

    public function replaceInTextContent()
    {

        add_filter('wp_get_attachment_image_src', [$this, 'src'], 20, 3);
        add_filter('wp_calculate_image_srcset', [$this, 'srcset'], 20, 5);
        add_filter('wp_get_attachment_metadata', [$this, 'generateVirtualSizes'], 20, 2);
    }

    public function replaceInCode()
    {

        add_filter('the_content', [$this, 'regexSrc'], 20);
    }

    public function replaceAvatars()
    {

        add_filter('get_avatar', [$this, 'userAvatarHtml'], 20, 3);
        add_filter('get_avatar_data', [$this, 'userAvatarDataFallback'], 20, 2);
    }

    private static function isContainSizeStr($str)
    {
        preg_match("~(-\d+?x\d+?)\.\D{3,4}$~iU", $str, $m);

        if (isset($m[1])) {
            return $m[1];
        }

        return false;
    }

    public function userAvatarDataFallback($args, $identificator)
    {

        if (filter_var($args['url'], FILTER_VALIDATE_URL)) {
            $args['url'] = $this->proxy->builder(
                apply_filters(
                    'ImageProxy__image-avatar-data-src',
                    [
                        'width' => $args['width'],
                        'height' => $args['height'],
                    ],
                    $args['url'],
                    $identificator
                ),
                $args['url']
            );
        }

        return $args;
    }

    public function userAvatarHtml($avatar, $identificator, $size)
    {

        $src = $this->getAttribute('src', $avatar);

        if (self::checkComplete($src)) {

            $subString = self::isContainSizeStr($src);

            $newSrc = $src;

            if (!empty($subString)) {
                $newSrc = str_replace($subString, '', $src);
            }

            $newSrc = $this->replaceHost($newSrc);

            $newSrc = $this->proxy->builder(
                apply_filters(
                    'ImageProxy__image-avatar-src',
                    [
                        'width' => $size,
                        'height' => $size
                    ],
                    $src,
                    $identificator,
                    $size
                ),
                $newSrc
            );

            $avatar = str_replace($src, $newSrc, $avatar);

        }

        return $avatar;
    }

    private function excludeAdminAjaxActions()
    {
        $action = isset($_REQUEST['action']) ? $_REQUEST['action'] : false;

        $exclude = ['query-attachments'];

        $exclude = apply_filters(
            'ImageProxy__site-ajax-actions-exclude',
            $exclude,
            $action
        );

        if (in_array($action, $exclude)) {
            return false;
        }

        return true;
    }

    private function calculateSizesBySourceImage($source, $base, $id)
    {

        $c = 100;
        $width = $source['width'];
        $height = $source['height'];
        $out = [];

        if ($c <= $width && $c <= $height) {

            $p = $width / $height;
            $i = $width;

            for (; $i > $c; $i = $i - $c) {

                if ($c < $i) {

                    $w = $i;
                    $h = (int)round($w / $p);

                    $out["image_{$w}x{$h}"] = [
                        'file' => $this->addStrSize($base, "-{$w}x{$h}"),
                        'width' => $w,
                        'height' => $h,
                        'mime-type' => get_post_mime_type($id)
                    ];
                }
            }

            if (!empty($out)) {
                return $out;
            }
        }

        return false;
    }

    private function replaceHost($url)
    {

        if (empty($this->siteUrl)) {
            return $url;
        }

        $host = parse_url($url, PHP_URL_HOST);
        $scheme = parse_url($url, PHP_URL_SCHEME);

        $newHost = parse_url($this->siteUrl, PHP_URL_HOST);
        $newScheme = parse_url($this->siteUrl, PHP_URL_SCHEME);

        return str_replace("{$scheme}://{$host}", "{$newScheme}://{$newHost}", $url);
    }

    /**
     * @return array
     *
     * Получаем размеры стандарных миниатюр
     */
    private function getDefaultImageSize($touch = true)
    {
        $defaultSizes = ['thumbnail', 'medium', 'medium_large', 'large'];

        $out = [];
        foreach ($defaultSizes as $defaultSize) {

            $width = (int)get_option("{$defaultSize}_size_w", 0);
            $height = (int)get_option("{$defaultSize}_size_h", 0);

            if (!empty($width) && !empty($height)) {
                $out[$defaultSize] = [
                    'width' => $width,
                    'height' => $height,
                    'crop' => get_option("{$defaultSize}_crop", false),
                ];
            }
        }

        if ($touch) {
            $out["image_512x512"] = [
                'width' => 512,
                'height' => 512,
                'crop' => true,
            ];

            $out["image_270x270"] = [
                'width' => 270,
                'height' => 270,
                'crop' => true,
            ];

            $out["image_192x192"] = [
                'width' => 192,
                'height' => 192,
                'crop' => true,
            ];

            $out["image_180x180"] = [
                'width' => 180,
                'height' => 180,
                'crop' => true,
            ];

            $out["image_152x152"] = [
                'width' => 152,
                'height' => 152,
                'crop' => true,
            ];

            $out["image_120x120"] = [
                'width' => 120,
                'height' => 120,
                'crop' => true,
            ];

            $out["image_76x76"] = [
                'width' => 76,
                'height' => 76,
                'crop' => true,
            ];

            $out["image_32x32"] = [
                'width' => 32,
                'height' => 32,
                'crop' => true,
            ];
        }

        return $out;
    }

    /**
     * Отколючает создание (ядром WordPress) дополнительных размеров изображений
     *
     * @param $sizes
     *
     * @return array
     */
    public function disableGenerateThumbnails($sizes)
    {
        $this->cliHelper();

        $newSizes = $this->getDefaultImageSize(false);

        $newSizes = apply_filters('ImageProxy__image-disable-sizes', $newSizes, $sizes);

        return $newSizes;
    }

    /**
     * Remove intermediate image sizes inside WP CLI
     */
    private function cliHelper()
    {
        if (defined('WP_CLI') && WP_CLI) {
            remove_image_size('1536x1536');
            remove_image_size('2048x2048');
        }
    }

    /**
     * Создает виртуальные копи картинок путем подмены метаданных
     *
     * @param $data
     * @param $id
     *
     * @return mixed
     */
    public function generateVirtualSizes($data, $id)
    {

        if (apply_filters('ImageProxy__image-id-skip', false, $id)) {
            return $data;
        }

        $sizes = wp_get_additional_image_sizes();
        $sizes = array_merge($sizes, $this->getDefaultImageSize());
        $sizes = apply_filters('ImageProxy__image-default-virtual-sizes', $sizes);

        $baseName = basename($data['file']);

        $newSizes = [];

        foreach ($sizes as $key => $val) {

            $p = $data['width'] / $data['height'];

            unset($val['crop']);

            $newSizes[$key] = array_merge(
                ['file' => $baseName],
                $val,
                ['mime-type' => get_post_mime_type($id)]
            );

            if (empty($val['height'])) {

                if (!empty($newSizes[$key]['width'])) {
                    $newSizes[$key]['height'] = (int)round($data['width'] / $p);
                }
            }

            $newSizes[$key]['file'] = $this->addStrSize(
                $baseName,
                "-{$newSizes[ $key ]['width']}x{$newSizes[ $key ]['height']}"
            );

        }

        $newSizes["image_{$data['width']}x{$data['height']}"] = [
            'file' => $this->addStrSize($baseName, "-{$data['width']}x{$data['height']}"),
            'width' => $data['width'],
            'height' => $data['height'],
            'mime-type' => get_post_mime_type($id)
        ];

        foreach ($newSizes as $key => $val) {

            $adinational[$key] = $val;

            $tmp = $this->calculateSizesBySourceImage($val, $baseName, $id);

            if (false !== $tmp) {
                $adinational = array_merge($adinational, $tmp);
            }

        }

        $data['sizes'] = $adinational;

        return $data;
    }

    private function srcsetSizesCropFinder($width, $height)
    {
        $sizes = wp_get_registered_image_subsizes();

        foreach ($sizes as $size) {
            if ($width == $size['width'] && $height == $size['height']) {
                return $size['crop'];
            }
        }

        return ['center', 'center'];
    }

    public function srcset($sources, $sizeArray, $imageSrc, $imageMeta, $id)
    {
        $id = (int)$id;

        if (apply_filters('ImageProxy__image-id-skip', false, $id)) {
            return $sources;
        }

        $sizes = $imageMeta['sizes'];
        $sizesByName = function ($name) use ($sizes) {
            $name = basename($name);

            foreach ($sizes as $size) {

                if ($name == $size['file']) {
                    return $size;
                }
            }

            return false;
        };
        $dirUpload = wp_get_upload_dir();
        $originFile = $dirUpload['baseurl'] . "/" . $imageMeta['file'];
        $originFile = $this->replaceHost($originFile);

        $out = [];

        foreach ($sources as $source) {
            $findSize = $sizesByName($source['url']);

            if (!empty($findSize)) {

                if (0 < $findSize['width'] || 0 < $findSize['height']) {

                    $outSize = $this->srcsetSizesCropFinder($sizeArray[0], $sizeArray[1]);

                    $source['url'] = $this->proxy->builder(
                        apply_filters(
                            'ImageProxy__image-attachment-srcset000',
                            array_merge(
                                [
                                    'width' => $findSize['width'],
                                    'height' => $findSize['height'],
                                ],
                                $this->cropType($outSize)
                            ),
                            $originFile,
                            $id
                        ),
                        $originFile
                    );

                }

                $source = array_diff($source, [false]);

                $out[] = $source;
            }

        }
        
        return $out;
    }


    /**
     * @param $origWidth
     * @param $origHeight
     * @param $destWidth
     * @param $destHeight
     * @param bool $crop
     *
     * @return array
     * @see image_resize_dimensions, imagecopyresampled
     */
    private function cropHelper($origWidth, $origHeight, $destWidth, $destHeight, $crop = false)
    {
        if (empty($destHeight)) {
            if ($origWidth < $destWidth) {
                return ['width' => 0, 'height' => 0];
            }
        } elseif (empty($destWidth)) {
            if ($origHeight < $destHeight) {
                return ['width' => 0, 'height' => 0];
            }
        } else {
            if ($origWidth < $destWidth && $origHeight < $destHeight) {
                return ['width' => 0, 'height' => 0];
            }
        }

        if ($crop) {
            $aspectRatio = $origWidth / $origHeight;
            $widthNew = min($destWidth, $origWidth);
            $heightNew = min($destHeight, $origHeight);

            if (!$widthNew) {
                $widthNew = (int)round($heightNew * $aspectRatio);
            }

            if (!$heightNew) {
                $heightNew = (int)round($widthNew / $aspectRatio);
            }

        } else {

            list($widthNew, $heightNew) = wp_constrain_dimensions($origWidth, $origHeight, $destWidth, $destHeight);
        }

        return ['width' => (int)$widthNew, 'height' => (int)$heightNew];
    }

    /**
     * Заменяет изображения в src картнок WordPress
     *
     * @param $image
     * @param $id
     * @param $size
     *
     * @return mixed
     */
    public function src($image, $id, $size)
    {

        if (apply_filters('ImageProxy__image-id-skip', false, $id)) {
            return $image;
        }

        $meta = wp_get_attachment_metadata($id);
        $sizes = wp_get_additional_image_sizes();
        $sizes = array_merge($sizes, $this->getDefaultImageSize());

        $s = "?origin=" . _wp_get_attachment_relative_path($image[0] . "/" . basename($image[0]));
        $image[0] = $this->replaceHost($image['0']);
        $image[0] = $this->replaceHost(wp_get_attachment_url($id));

        if (isset($image[0])) {

            if (is_string($size)) {

                $sizeMeta = (isset($sizes[$size]) ? $sizes[$size] : 0);
                $builderArgs = $this->cropHelper(
                    $meta['width'], $meta['height'],
                    $sizeMeta['width'], $sizeMeta['height'],
                    $sizeMeta['crop']
                );

                $image[0] = $this->proxy->builder(
                    apply_filters(
                        'ImageProxy__image-attachment-src',
                        array_merge($builderArgs, $this->cropType($sizeMeta['crop'])),
                        $image[0],
                        $id
                    ),
                    $image[0]
                );

            } elseif (is_array($size)) {
                $url = wp_get_attachment_url($id);
                $url = $this->replaceHost($url);

                $image[0] = $this->proxy->builder(
                    apply_filters(
                        'ImageProxy__image-attachment-src',
                        [
                            'width' => !isset($size[0]) ? 0 : $size[0],
                            'height' => !isset($size[1]) ? 0 : $size[1],
                        ],
                        $url,
                        $id
                    ),
                    $url
                );
            }

        }

        $image[0] = $image[0] . $s;


        return $image;
    }

    /**
     * Определят тип обрезки картинки и конвертирует в формать бекенда
     *
     * @param $crop
     *
     * @return array
     */
    public function cropType($crop)
    {

        if (is_array($crop)) {

            if (empty($crop) || 2 > count($crop)) {
                return [
                    'g' => [
                        'gravity_type' => 'ce',
                    ]
                ];
            }

            if (!in_array($crop[0], ['left', 'right', 'center'])) {
                return [
                    'g' => [
                        'gravity_type' => 'ce',
                    ]
                ];
            }

            if (!in_array($crop[1], ['top', 'bottom', 'center'])) {
                return [
                    'g' => [
                        'gravity_type' => 'ce',
                    ]
                ];
            }

            $list = [
                'center|center' => 'ce',
                'left|center' => 'we',
                'right|center' => 'ea',
                'center|top' => 'no',
                'center|bottom' => 'so',
                'left|top' => 'nowe',
                'right|top' => 'noea',
                'right|bottom' => 'soea',
                'left|bottom' => 'sowe',
            ];
            $cropString = implode('|', $crop);

            return [
                'g' => [
                    'gravity_type' => $list[$cropString],
                ]
            ];

        } else {

            if (false == $crop) {

                // по стороне
                return [
                    'g' => [
                        'gravity_type' => 'ce',
                        'x_offset' => 0,
                        'y_offset' => 0,
                    ],
                ];
            }

            return [
                'g' => [
                    'gravity_type' => 'ce',
                ]
            ];

        }
    }

    public static function checkComplete($url)
    {
        $u = wp_upload_dir();
        $pattern = $u['baseurl'];

        if (false === stristr($url, $pattern)) {
            return false;
        }

        return true;
    }

    /**
     * Находит картинки в тексте статьи и заменяет и подменяет их на url картинок в конвертере
     *
     * @param $str
     *
     * @return string|string[]
     */
    public function regexSrc($str)
    {

        preg_match_all('~<img.*>~im', $str, $images);

        $array = [];

        if (isset($images[0]) && !empty($images[0])) {

            foreach ($images[0] as $image) {

                $src = $this->getAttribute('src', $image);
                if (!apply_filters('ImageProxy__image-src-skip', false, $src)) {
                    if (self::checkComplete($src)) {

                        $height = $this->getAttribute('height', $image);
                        $width = $this->getAttribute('width', $image);

                        $imageSrc = $src;

                        $imageSrc = $this->replaceHost($imageSrc);

                        $array[$src] = $this->proxy->builder(
                            apply_filters(
                                'ImageProxy__image-content-src',
                                [
                                    'width' => $width,
                                    'height' => $height
                                ],
                                $imageSrc
                            )
                            ,
                            $imageSrc
                        );

                    }
                }

            }

            if (!empty($array)) {
                return str_replace(array_keys($array), array_values($array), $str);
            }
        }

        return $str;
    }

    /**
     * Get html attribute by name
     *
     * @param $str
     * @param $atr
     *
     * @return mixed
     */
    public function getAttribute($atr, $str)
    {
        preg_match("~{$atr}=[\"|'](.*)[\"|']\s~imU", $str, $m);

        if (isset($m[1])) {
            return $m[1];
        }

        return '';
    }

    private function getFileExtension($string)
    {
        $info = new SplFileInfo($string);

        return $info->getExtension();
    }

    private function addStrSize($fileString, $fileSizeString)
    {

        $extension = $this->getFileExtension($fileString);

        $pattern = "(\.{$extension}$)";

        return preg_replace(
            "/$pattern/i",
            "{$fileSizeString}$1",
            $fileString
        );
    }

}
