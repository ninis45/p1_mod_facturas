<?php defined('BASEPATH') or exit('No direct script access allowed');


class Factura
{
	

	// --------------------------------------------------------------------------
    public static $_path;
    public function __construct()
    {
        ci()->config->load('files/files');
        ci()->lang->load('facturas/factura');
        self::$_path = FCPATH.rtrim(ci()->config->item('files:path'), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
    }
    public static function ValidXML($id,$extra=array())
    {
        $result = array(
        
            'status'   => true,
            'messages' => array(),
            'data'     => array()
        );
        
        $file = Files::get_file($id);
        
        libxml_use_internal_errors(true);
        
        $xml = new DOMDocument();
        $xsl = new DOMDocument();
        
        $proc = new XSLTProcessor;
        
       
        $data = array();
       
        
        $xml->load(self::$_path.'/'.$file['data']->filename);
        
        $root = $xml->getElementsByTagNameNS('http://www.sat.gob.mx/cfd/3', '*');///$xml->getElementsByTagName('Comprobante');
        
       
        foreach($root as $element)
        {
            ($element->getAttribute('version') || $element->getAttribute('Version')) AND $data['version']     = $element->getAttribute('version')?$element->getAttribute('version'):$element->getAttribute('Version');
            ($element->getAttribute('sello') || $element->getAttribute('Sello')) AND $data['sello']  = $element->getAttribute('sello')?$element->getAttribute('sello'):$element->getAttribute('Sello');
            ($element->getAttribute('certificado') || $element->getAttribute('Certificado')) AND $data['cert'] = $element->getAttribute('certificado')?$element->getAttribute('certificado'):$element->getAttribute('Certificado');
            
            if(empty($extra)== false)
            {
                foreach($extra as $ele)
                {
                    ($element->getAttribute($ele) || $element->getAttribute(ucfirst($ele))) AND $data[$ele] = $element->getAttribute($ele)?$element->getAttribute($ele):$element->getAttribute(ucfirst($ele));
                }
            }
            
            //if($element->getElementsByTagName('Complemento'))
              //  $complementos = $element->getElementsByTagName('Complemento');
                
            //if($element->getElementsByTagName('Addenda'))
              //  $addenda     = $element->getElementsByTagName('Addenda');
        }
        
        ///$elements     = $xml->getElementsByTagNameNS('http://www.sat.gob.mx/cfd/3', '*');
        $complementos = $xml->getElementsByTagNameNS('http://www.sat.gob.mx/TimbreFiscalDigital','*');
        $addenda      = $xml->getElementsByTagNameNS('http://www.sat.gob.mx/cfd/3','Addenda');
        
        
        foreach($addenda as $element)
        {
            
            
            $xml->documentElement->removeChild($element);
        }
       
        
        
        /*
      
        foreach($elements as $element)
        {
            
            
            $element->getAttribute('version') AND $data['version']     = $element->getAttribute('version');
            $element->getAttribute('sello') AND $data['sello']  = $element->getAttribute('sello');
            $element->getAttribute('certificado') AND $data['cert'] = $element->getAttribute('certificado');
            
            if(empty($extra)== false)
            {
                foreach($extra as $ele)
                {
                    $element->getAttribute($ele) AND $data[$ele] = $element->getAttribute($ele);
                }
            }
           
            
        }*/
        
        $openssl_algo = OPENSSL_ALGO_SHA1;
        switch($data['version'])
        {
            /*case '3.0':
            
                $validate = $xml->schemaValidate(dirname(__FILE__).'/files/cfdv3.xsd');
                $xsl->load(dirname(__FILE__).'/files/cadenaoriginal_3_0.xslt');
            break;
            case '3.2':
                $validate = $xml->schemaValidate(dirname(__FILE__).'/files/cfdv32.xsd');
                $xsl->load(dirname(__FILE__).'/files/cadenaoriginal_3_2.xslt');
            break;
            default:
                $validate = false;
            break;
            */
            case '3.0':
            
                $validate = $xml->schemaValidate(self::$_path.'/facturacion/cfdv3.xsd');
                $xsl->load(self::$_path.'/facturacion/cadenaoriginal_3_0.xslt');
            break;
            case '3.2':
                $validate = $xml->schemaValidate(self::$_path.'/facturacion/cfdv32.xsd');
                $xsl->load(self::$_path.'/facturacion/cadenaoriginal_3_2.xslt');
                
                
            break;
             case '3.3':
                $validate = $xml->schemaValidate(self::$_path.'/facturacion/cfdv33.xsd');
                $xsl->load(self::$_path.'/facturacion/cadenaoriginal_3_3.xslt');
                
                $openssl_algo = OPENSSL_ALGO_SHA256;
            break;
            default:
                $validate = false;
            break;
        }
        
        if(!$validate)
        {
            
            $result['status']     = false;
            $result['messages'][] = array('code'=>0,'message'=>lang('factura:error_xml'));
        }
        else{
            
            $result['messages'][] = array('code'=>1,'message'=>lang('factura:success_xml'));//Estructura del XML válida
            foreach($complementos as $element)
            {
            
                $element->getAttribute('UUID') AND $data['UUID']     = $element->getAttribute('UUID');          
            
           
            
            }
            
            if(!$data['UUID'])
            {
            
                $result['messages'][]=array('code'=>0,'message'=> lang('factura:error_timbrado'));
                
            }
            
            
            $proc->importStyleSheet($xsl); 
            $cadena = $proc->transformToXML($xml);
            
            
            if(!$cadena)
            {
                $result['messages'][] = array('code'=>0,'message'=>lang('factura:error_cadena'));
            }
            
            $pem = (sizeof($data['cert'])<=1) ? $data['cert'] : $data['cert'][0];
           
            $pem = preg_replace("[\n|\r|\n\r]", '', $pem);
            $pem = preg_replace('/\s\s+/', '', $pem); 
            $cert = "-----BEGIN CERTIFICATE-----\n".chunk_split($pem,64)."-----END CERTIFICATE-----\n";
            
            
            $pubkeyid = openssl_get_publickey(openssl_x509_read($cert));
            
            
        
            if(!$pubkeyid)
            {
                
                
                 $result['messages'][] = array('code'=>0,'message'=>lang('factura:error_cert'));
            }
            
            
            $sello = openssl_verify($cadena, 
                     base64_decode($data['sello']), 
                     $pubkeyid, 
                     $openssl_algo);
                     
            if(!$sello)
            {
                $result['messages'][] = array('code'=>0,'message'=> lang('factura:error_sello'));
                
            }
            else
            {
                $result['messages'][] = array('code'=>1,'message'=> lang('factura:success_sello'));
            }
            $result['data'] = $data;
        }
        
        return $result;
        //print_r($data);
    }
    
    static function _display_xml_errors() {
        global $texto;
        $lineas = explode("\n", $texto);
        $errors = libxml_get_errors();
        echo "<pre>";
        foreach ($errors as $error) {
            echo self::display_xml_error($error, $lineas);
        }
        echo "</pre>";
        libxml_clear_errors();
    }
    /// }}}}
    // {{{ display_xml_error
    static function display_xml_error($error, $lineas) {
        $return  = htmlspecialchars($lineas[$error->line - 1]) . "\n";
        $return .= str_repeat('-', $error->column) . "^\n";
        switch ($error->level) {
            case LIBXML_ERR_WARNING:
                $return .= "Warning $error->code: ";
                break;
             case LIBXML_ERR_ERROR:
                $return .= "Error $error->code: ";
                break;
            case LIBXML_ERR_FATAL:
                $return .= "Fatal Error $error->code: ";
                break;
        }
        $return .= trim($error->message) .
                   "\n  Linea: $error->line" .
                   "\n  Columna: $error->column";
        echo "$return\n\n--------------------------------------------\n\n";
    }
}
?>