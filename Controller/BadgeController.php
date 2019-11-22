<?php

/*
 * @copyright   2016 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticBadgeGeneratorBundle\Controller;

use Mautic\CoreBundle\Controller\AbstractStandardFormController;
use Mautic\CoreBundle\Helper\ArrayHelper;
use Mautic\CoreBundle\Helper\EncryptionHelper;
use Mautic\CoreBundle\Helper\PathsHelper;
use Mautic\LeadBundle\Controller\EntityContactsTrait;
use MauticPlugin\MauticBadgeGeneratorBundle\Entity\Badge;
use MauticPlugin\MauticBadgeGeneratorBundle\Generator\BadgeGenerator;
use MauticPlugin\MauticBadgeGeneratorBundle\Generator\RoundedImageGenerator;
use MauticPlugin\MauticBadgeGeneratorBundle\Token\BadgeHashGenerator;
use MauticPlugin\MauticBadgeGeneratorBundle\Uploader\BadgeUploader;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class BadgeController extends AbstractStandardFormController
{
    use EntityContactsTrait;

    /**
     * @var string
     */
    private $source;

    /**
     * {@inheritdoc}
     */
    protected function getJsLoadMethodPrefix()
    {
        return 'badge.badge';
    }

    /**
     * {@inheritdoc}
     */
    protected function getModelName()
    {
        return 'badge.badge';
    }

    /**
     * {@inheritdoc}
     */
    protected function getRouteBase()
    {
        return 'badge_generator';
    }

    /***
     * @param null $objectId
     *
     * @return string
     */
    protected function getSessionBase($objectId = null)
    {
        return 'badgeGenerator'.(($objectId) ? '.'.$objectId : '');
    }

    /**
     * @return string
     */
    protected function getControllerBase()
    {
        return 'MauticBadgeGeneratorBundle:Badge';
    }

    /**
     * @return \Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function batchDeleteAction()
    {
        return $this->batchDeleteStandard();
    }

    /**
     * @param $objectId
     *
     * @return \Mautic\CoreBundle\Controller\Response|\Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function cloneAction($objectId)
    {
        return $this->cloneStandard($objectId);
    }

    /**
     * @param      $objectId
     * @param bool $ignorePost
     *
     * @return \Mautic\CoreBundle\Controller\Response|\Symfony\Component\HttpFoundation\JsonResponse
     */
    public function editAction($objectId, $ignorePost = false)
    {
        return parent::editStandard($objectId, $ignorePost);
    }

    /**
     * @param Badge $badge
     * @param Badge $oldBadge
     *
     * @return array|void
     */
    protected function afterEntityClone($badge, $oldBadge)
    {
        $this->get('session')->set('clonedSource', $oldBadge->getSource());
        $this->get('session')->set('clonedProperties', $oldBadge->getProperties());
    }

    /**
     * @param Badge    $entity
     * @param Form     $form
     * @param          $action
     * @param null     $objectId
     * @param bool     $isClone
     *
     * @return bool
     */
    protected function beforeEntitySave($entity, Form $form, $action, $objectId = null, $isClone = false)
    {
        /** @var BadgeUploader $uploader */
        $uploader = $this->get('mautic.badge.uploader');
        $uploader->uploadFiles($entity, $this->request, $form);
        $uploader->uploadPropertiesFiles($entity, $this->request);

        if ($isClone) {
            if (!$entity->getSource() && $this->get('session')->has('clonedSource')) {
                $entity->setSource($this->get('session')->get('clonedSource'));
            }
            if ($this->get('session')->has('clonedProperties')) {
                $properties = $entity->getProperties();
                foreach ($properties as $key => $property) {
                    if ('custom' === ArrayHelper::getValue('font', $property) &&
                        !ArrayHelper::getValue('ttf', $property)) {
                        $clonedProperties        = $this->get('session')->get('clonedProperties');
                        $properties[$key]['ttf'] = $clonedProperties[$key]['ttf'];
                    }
                }
                $entity->setProperties($properties);
            }
        }

        return true;
    }


    /**
     * @param int $page
     *
     * @return \Mautic\CoreBundle\Controller\Response|\Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function indexAction($page = 1)
    {
        return $this->indexStandard($page);
    }

    /**
     * @return \Mautic\CoreBundle\Controller\Response|\Symfony\Component\HttpFoundation\JsonResponse
     */
    public function newAction()
    {
        return $this->newStandard();
    }

    /**
     * @param $objectId
     *
     * @return array|\Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function viewAction($objectId)
    {
        //set the page we came from
        $page      = 1;
        $returnUrl = $this->generateUrl('mautic_badge_generator_index', ['page' => $page]);

        return $this->postActionRedirect(
            [
                'returnUrl'       => $returnUrl,
                'viewParameters'  => ['page' => $page],
                'contentTemplate' => 'MauticBadgeGeneratorBundle:Badge:index',
                'passthroughVars' => [
                    'activeLink'    => '#mautic_badge_generator_contacts',
                    'mauticContent' => 'badge',
                ],
            ]
        );
    }

    /**
     * @param $args
     * @param $action
     *
     * @return mixed
     */
    protected function getViewArguments(array $args, $action)
    {
        $viewParameters = [];

        switch ($action) {
            case 'new':
            case 'edit':
                if ($integration = $this->get('mautic.helper.integration')->getIntegrationObject('BadgeGenerator')) {
                    $integrationSettings                   = $integration->mergeConfigToFeatureSettings();
                    $viewParameters['numberOfTextBlock']   = ArrayHelper::getValue(
                        'numberOfTextBlocks',
                        $integrationSettings,
                        BadgeGenerator::NUMBER_OF_DEFAULT_TEXT_BLOCKS
                    );
                    $viewParameters['numberOfImagesBlock'] = ArrayHelper::getValue(
                        'numberOfImagesBlocks',
                        $integrationSettings,
                        BadgeGenerator::NUMBER_OF_DEFAULT_IMAGES_BLOCKS
                    );
                }
            case 'index':
            case 'edit':
                $viewParameters['uploader'] = $this->get('mautic.badge.uploader');
                break;

        }

        $args['viewParameters'] = array_merge($args['viewParameters'], $viewParameters);

        return $args;
    }

    /**
     * @param $objectId
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    protected function deleteAction($objectId)
    {
        return $this->deleteStandard($objectId);
    }

    /**
     * @param      $objectId
     * @param null $contactId
     */
    public function generateAction($objectId, $contactId = null, $hash = null)
    {
        /** @var BadgeGenerator $badgeGenerator */
        $badgeGenerator = $this->get('mautic.badge.generator');
        /** @var BadgeHashGenerator $hashGenerator */
        $hashGenerator = $this->get('mautic.badge.hash.generator');
        if (!$hashGenerator->isValidHash($contactId, $hash)) {
            return $this->accessDenied();
        }
        try {
            return $badgeGenerator->generate($objectId, $contactId, $hash, $hashGenerator->isAdmin());
        } catch (\Exception $exception) {
            return $this->accessDenied();
        }
    }

    /**
     * @return JsonResponse|Response
     */
    public function listViewAction()
    {
        $sessionVar = 'badge';
        $this->get('session')->set('mautic.'.$sessionVar.'.contact.orderby', 'l.firstname');
        $this->get('session')->set('mautic.'.$sessionVar.'.contact.orderbydir', 'ASC');

        return $this->delegateView([
            'viewParameters' => [
                'permissions' => [],
                'contacts'    => $this->forward(
                    'MauticBadgeGeneratorBundle:Badge:contacts',
                    [
                        'objectId'=> 1,
                        'page'       => $this->get('session')->get('mautic.badge.contact.page', 1),
                        'ignoreAjax' => true,
                    ]
                )->getContent(),
            ],
            'contentTemplate' => 'MauticBadgeGeneratorBundle:Badge:contact_list.html.php',
            'passthroughVars' => [
                'mauticContent' => 'badge',
            ],
        ]);
    }

    /**
     * @param null $encryptImageUrl
     */
    public function imageAction($encryptImageUrl = null, $width = 400)
    {
        /** @var EncryptionHelper $encryptionHelper */
        $encryptionHelper = $this->get('mautic.helper.encryption');
        $filename         = $encryptionHelper->decrypt(unserialize($encryptImageUrl));;

        /** @var RoundedImageGenerator $roundedImageGenerator */
        $roundedImageGenerator = $this->get('mautic.badge.rounded.image.generator');
        $roundedImageGenerator->generate($filename, $width);
        return;
    }

    /**
     * @param     $objectId
     * @param int $page
     *
     * @return JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse|Response
     */
    public function contactsAction($objectId, $page = 1)
    {
        $sessionVar = 'badge';

        $filters =
            [
                'date_identified' => [
                    'col'   => 'date_identified',
                    'expr'   => 'isNotNull',
                    'value'  => '',
                ]
        ];
        return $this->generateContactsGrid(
            1,
            $page,
            '',
            $sessionVar,
            '',
            '',
            '',
            $filters
        );
    }

    public function  Crop_ByRadius($source_url,$Radius="0px" ,$Keep_SourceFile = TRUE){

        if( $Radius == NULL )
            return FALSE;




        $ImageInfo = getimagesize($source_url);
        $w = $ImageInfo[0];
        $h = $ImageInfo[1];
        $mime = $ImageInfo['mime'];

        if( $mime != "image/jpeg" && $mime != "image/jpg" && $mime != "image/png")
            return FALSE;

        if( strpos($Radius,"%") !== FALSE ){
            //$Radius by Cent
            $Radius = intval( str_replace("%","",$Radius) );
            $Smallest_Side = $w <= $h ? $w : $h;
            $Radius = $Smallest_Side * $Radius / 100;

        }else{
            $Radius = strtolower($Radius);
            $Radius = str_replace("px","",$Radius);
        }

        $Radius = is_numeric($Radius) ? intval($Radius) : 0;

        if( $Radius == 0 ) return FALSE;
        $src = imagecreatefromstring(file_get_contents($source_url));
        $newpic = imagecreatetruecolor($w,$h);
        imagealphablending($newpic,false);
        $transparent = imagecolorallocatealpha($newpic, 0, 0, 0, 127);
        //$transparent = imagecolorallocatealpha($newpic, 255, 0, 0, 0);//RED For Test

        $r = $Radius / 2;

        /********************** Pixel step config ********************************/

        $Pixel_Step_def = 0.4;//smaller step take longer time! if set $Pixel_Step=0.1 result is better than  $Pixel_Step=1 but it take longer time!

        //We select the pixels we are sure are in range, to Take up the bigger steps and shorten the processing time

        $Sure_x_Start = $Radius +1;
        $Sure_x_End = $w - $Radius -1;
        $Sure_y_Start = $Radius +1;
        $Sure_y_End = $h - $Radius -1;
        if( $w <= $h ){
            //We want to use the larger side to make processing shorter
            $Use_x_Sure = FALSE;
            $Use_y_Sure = TRUE;
        }else{
            $Use_x_Sure = TRUE;
            $Use_y_Sure = FALSE;
        }
        /********************** Pixel step config END********************************/

        $Pixel_Step = $Pixel_Step_def;
        for( $x=0; $x < $w ; $x+=$Pixel_Step ){

            if( $Use_x_Sure && $x > $Sure_x_Start && $x < $Sure_x_End ) $Pixel_Step = 1;else $Pixel_Step = $Pixel_Step_def;

            for( $y=0; $y < $h ; $y+=$Pixel_Step){
                if( $Use_y_Sure && $y > $Sure_y_Start && $y < $Sure_y_End ) $Pixel_Step = 1;else $Pixel_Step = $Pixel_Step_def;

                $c = imagecolorat($src,$x,$y);

                $_x = ($x - $Radius) /2;
                $_y = ($y - $Radius) /2;
                $Inner_Circle = ( ( ($_x*$_x) + ($_y*$_y) ) < ($r*$r) );
                $top_Left = ($x > $Radius || $y > $Radius) || $Inner_Circle;

                $_x = ($x - $Radius) /2 - ($w/2 - $Radius);
                $_y = ($y - $Radius) /2;
                $Inner_Circle = ( ( ($_x*$_x) + ($_y*$_y) ) < ($r*$r) );
                $top_Right = ($x < ($w - $Radius) || $y > $Radius) || $Inner_Circle;

                $_x = ($x - $Radius) /2;
                $_y = ($y - $Radius) /2 - ($h/2 - $Radius);
                $Inner_Circle = ( ( ($_x*$_x) + ($_y*$_y) ) < ($r*$r) );
                $Bottom_Left =  ($x > $Radius || $y < ($h - $Radius) ) || $Inner_Circle;

                $_x = ($x - $Radius) /2 - ($w/2 - $Radius);
                $_y = ($y - $Radius) /2 - ($h/2 - $Radius);
                $Inner_Circle = ( ( ($_x*$_x) + ($_y*$_y) ) < ($r*$r) );
                $Bottom_Right = ($x < ($w - $Radius) || $y < ($h - $Radius) ) || $Inner_Circle;

                if($top_Left && $top_Right && $Bottom_Left && $Bottom_Right ){

                    imagesetpixel($newpic,$x,$y,$c);

                }else{
                    imagesetpixel($newpic,$x,$y,$transparent);
                }

            }
        }



        imagesavealpha($newpic, true);
        header('Content-type: image/png');
        imagepng($newpic);
        imagedestroy($newpic);
        imagedestroy($src);

    }
    //resize and crop image by center
    function resize_crop_image($max_width, $max_height, $source_file, $dst_dir, $quality = 80){
        $imgsize = getimagesize($source_file);
        $width = $imgsize[0];
        $height = $imgsize[1];
        $mime = $imgsize['mime'];

        switch($mime){
            case 'image/gif':
                $image_create = "imagecreatefromgif";
                $image = "imagegif";
                break;

            case 'image/png':
                $image_create = "imagecreatefrompng";
                $image = "imagepng";
                $quality = 7;
                break;

            case 'image/jpeg':
                $image_create = "imagecreatefromjpeg";
                $image = "imagejpeg";
                $quality = 80;
                break;

            default:
                return false;
                break;
        }

        $dst_img = imagecreatetruecolor($max_width, $max_height);
        $src_img = $image_create($source_file);

        $width_new = $height * $max_width / $max_height;
        $height_new = $width * $max_height / $max_width;
        //if the new width is greater than the actual width of the image, then the height is too large and the rest cut off, or vice versa
        if($width_new > $width){
            //cut point by height
            $h_point = (($height - $height_new) / 2);
            //copy image
            imagecopyresampled($dst_img, $src_img, 0, 0, 0, $h_point, $max_width, $max_height, $width, $height_new);
        }else{
            //cut point by width
            $w_point = (($width - $width_new) / 2);
            imagecopyresampled($dst_img, $src_img, 0, 0, $w_point, 0, $max_width, $max_height, $width_new, $height);
        }

        $image($dst_img, $dst_dir, $quality);

        if($dst_img)imagedestroy($dst_img);
        if($src_img)imagedestroy($src_img);
    }
}

class SimpleImage {

    var $image;
    var $image_type;

    function load($filename) {

        $image_info = getimagesize($filename);
        $this->image_type = $image_info[2];
        if( $this->image_type == IMAGETYPE_JPEG ) {

            $this->image = imagecreatefromjpeg($filename);
        } elseif( $this->image_type == IMAGETYPE_GIF ) {

            $this->image = imagecreatefromgif($filename);
        } elseif( $this->image_type == IMAGETYPE_PNG ) {

            $this->image = imagecreatefrompng($filename);
        }
    }
    function save($filename, $image_type=IMAGETYPE_JPEG, $compression=75, $permissions=null) {

        if( $image_type == IMAGETYPE_JPEG ) {
            imagejpeg($this->image,$filename,$compression);
        } elseif( $image_type == IMAGETYPE_GIF ) {

            imagegif($this->image,$filename);
        } elseif( $image_type == IMAGETYPE_PNG ) {

            imagepng($this->image,$filename);
        }
        if( $permissions != null) {

            chmod($filename,$permissions);
        }
    }
    function output($image_type=IMAGETYPE_JPEG) {

        if( $image_type == IMAGETYPE_JPEG ) {
            imagejpeg($this->image);
        } elseif( $image_type == IMAGETYPE_GIF ) {

            imagegif($this->image);
        } elseif( $image_type == IMAGETYPE_PNG ) {

            imagepng($this->image);
        }
    }
    function getWidth() {

        return imagesx($this->image);
    }
    function getHeight() {

        return imagesy($this->image);
    }
    function resizeToHeight($height) {

        $ratio = $height / $this->getHeight();
        $width = $this->getWidth() * $ratio;
        $this->resize($width,$height);
    }

    function resizeToWidth($width) {
        $ratio = $width / $this->getWidth();
        $height = $this->getheight() * $ratio;
        $this->resize($width,$height);
    }

    function scale($scale) {
        $width = $this->getWidth() * $scale/100;
        $height = $this->getheight() * $scale/100;
        $this->resize($width,$height);
    }

    function resize($width,$height) {
        $new_image = imagecreatetruecolor($width, $height);
        imagecopyresampled($new_image, $this->image, 0, 0, 0, 0, $width, $height, $this->getWidth(), $this->getHeight());
        $this->image = $new_image;
    }

}
