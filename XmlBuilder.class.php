<?php

define("FOLDER","****");

trait XmlUtils {
    /**
     * Funzione che controllo la corretta formattazione della data,genera un eccezione
     * se il valore passato è nullo,oppure è mal formattato
     *
     * @param string $data data come stringa
     * @return string
     */
    public function checkDate(string $data):string {

        if(is_null($data)) throw new InvalidArgumentException("La data è nulla.");

        if(strlen($data)!=10) //yyyy - mm - dd
            throw new InvalidArgumentException("Data è troppo lunga.");
    
        $array = explode("-",$data);
    
        if(count($array)!=3 ||
          !($array[2]  >  0 &&  $array[2] <= 31 && 
            $array[1]  >  0 &&  $array[1] <= 12 && 
            strlen($array[0])==4) ) throw new InvalidArgumentException("Data non valida."); 
        
        return $data; //yyyy-mm-dd
    }
    /**
     * Verifica la correttezza sintattica della Partita iva
     * @param string $pi
     * @return string
     */
    public function validatePI(string $pi):string  {

		$pi = (string) str_replace(" ", "",  $pi);
		$pi = (string) str_replace("\t", "", $pi);
		$pi = (string) str_replace("\r", "", $pi);
		$pi = (string) str_replace("\n", "", $pi);

        if(strlen($pi) == 0) 
            throw new Exception("Attenzione inserire partita iva");

		if(strlen($pi) != 11) 
            throw new Exception("La lunghezza della partita iva non è corretta");

		if(preg_match("/^[0-9]{11}\$/sD", $pi) !== 1) 
            throw new Exception("Alcuni di questi caratteri non possono essere inseriti nella partita iva!");

		$s = 0;
		for( $i = 0; $i < 11; $i++ ){
			$n = ord($pi[$i]) - ord('0');
			if( ($i & 1) == 1 ){
				$n *= 2;
				if( $n > 9 ) $n -= 9;
			}
			$s += $n;
		}
		if($s % 10 != 0) 
            throw new Exception("Non mi sembra corretto,sicuro che hai scritto bene?");

		return $pi;
	}
}

/** 
 *  Overview : Classe astratta che genera la struttura per le richieste XML all'agenzia delle entrate.
 *  Representation invariant : $nameOfFile != null && $nameOfFile != *empty* && $root != null && $requestType != null
 */
Abstract Class XmlBuilder {

    private string $nameOfFile;       // Nome del file finale  
    private DOMDocument $root;        // Radice del documento
    private DOMElement $requestType;  // Puntatore al nodo dove verrano inserire ulteriori informazioni dai sottotipi

    /**
     * Il Costruttore inizializza la struttura inserendo il pattern generale per ogni richiesta xml.
     * @requires $name != null && $name is not empty
     * @return  void
     */
    public function __construct(string $name) {


        if(is_null($name) || $name=="") throw new Exception("Nome del file non valido.");

        $this->root = new DOMDocument("1.0","UTF-8");
        $this->root->formatOutput = true;
        $this->nameOfFile = $name;

        // Pattern generale per ogni richiesta xml
        $massiveInput = $this->root->createElement('ns1:InputMassivo');
        $massiveInput->setAttribute('xsi:schemaLocation','http://www.sogei.it/InputPubblicountitled.xsd');
        $massiveInput->setAttribute('xmlns:ns1','http://www.sogei.it/InputPubblico');
        $massiveInput->setAttribute('xmlns:xsi','http://www.w3.org/2001/XMLSchema-instance');
        $this->root->appendChild($massiveInput); 

        $this->requestType = $this->root->createElement('ns1:TipoRichiesta');
        $massiveInput->appendChild($this->requestType);  
    }

    /**
     * Rappresenta una funzione che prende come parametro, il nome e l'eventuale valore da dare ad un nuovo nodo
     * ed il nodo "padre" dove attaccarlo. Se il nodo-padre non viene specificato, in nuovo nodo verrà attaccato
     * al root "TipoRichiesta". Restituisce il puntatore al nuovo nodo creato.
     *  
     * @requires $name != null && $name!="" && ($value !isnull => $value!="")
     * @param string $name 
     * @param string $value 
     * @param DOMElement $node 
     * @return DOMElement
     */
    protected function addElement(string $name,string $value = null,DOMElement $node = null):DOMElement{

        if(is_null($name) || $name=="") throw new Exception("Il nome del nodo non valido.");
        if(!is_null($value) && $value=="")   throw new Exception("Il valore del nodo non valido.");

        $newNode = $this->root->createElement($name,$value); // Creo il nuovo nodo

        if($node==null) $this->requestType->appendChild($newNode); // Aggiungo il nodo al root
        else $node->appendChild($newNode);  // Se specificato il padre 
        return $newNode;
    }

    /**
     * Prende un ingresso una array di oggetti di tipo XmlBuilder, ed il nome del file finale,
     * recupera tutti gli xml creati e restituisce lo zip(con gli xml) con il nome $zipname.
     * Se nell'array non è presente un oggetto che estenda XmlBuilder allora l'oggetto viene ignorato.
     * 
     * @requires $array !=null && $array isnot empty && $zipname!=null && $zipname isnot empty
     * @param array $array
     * @param string $zipname
     * @return string
     */
    public static function mergeAndZip(array $array,string $zipname):string {

        if(is_null($array) || count($array)==0) 
            throw new Exception("Array non è valido");
        
        if($zipname == "") 
            throw new Exception("Il nome del file non può essere nullo");

        $zip = new ZipArchive(); 
        $filename = FOLDER.$zipname.".zip";

        if(file_exists($filename)) unlink($filename);

        if ($zip->open($filename, ZipArchive::CREATE)!==TRUE) 
            throw new Exception("Errore nella creazione dello zip.");

        foreach($array as $doc) {
            if($doc instanceof XmlBuilder) $zip->addFile($doc->getfilename(),$doc->getname().".xml");
        }
        $zip->close();

        return $filename;
    }

    /**
     * Crea un file xml con l'albero precedentemnte costruito,salvando il file
     * nella cartella di default,con il nome passato dal costruttore
     * @return string
     */
    protected function tofile():string { return $this->root->save(FOLDER.$this->nameOfFile.".xml"); }

    public function __toString() { return $this->root->saveXML(); }

    /**
     * Cancella il file xml creato
     * @return void
     */
    public function free():void { unlink($this->getfilename()); }

    /**
     * Restituisce il pattern dell file creato.
     * @return string
     */
    public function getfilename():string { return FOLDER.$this->nameOfFile.".xml";  }

    /**
     * Restituisce il name del file
     * @return string
     */
    private function getname():string { return $this->nameOfFile; }
}

Class CorrispettiviXml extends XmlBuilder {

    use XmlUtils;

    public function __construct(string $from,string $to,string $piva) {

        $from = $this->checkDate($from) ; // possono lanciare eccezioni
        $to   = $this->checkDate($to) ;
        $piva = $this->validatePI($piva);

        parent::__construct("Corrispettivi_".$piva);

        $Corrispettivi = parent::addElement("ns1:Corrispettivi");

        parent::addElement("ns1:Richiesta","CORR",$Corrispettivi);
        $datarilevazione = parent::addElement("ns1:DataRilevazione",null,$Corrispettivi);

        parent::addElement("ns1:Da",$from, $datarilevazione);
        parent::addElement("ns1:A",$to, $datarilevazione);

        $pivalist = parent::addElement("ns1:ElencoPiva",null,$Corrispettivi);
        parent::addElement("ns1:Piva",$piva,$pivalist);

        parent::addElement("ns1:TipoCorrispettivo","RT",$Corrispettivi);

        $this->tofile();
    }

}

Class FattureClientiXml extends XmlBuilder {

    use XmlUtils;

    public function __construct(string $from,string $to,string $piva) {

        $from = $this->checkDate($from) ; // possono lanciare eccezioni
        $to   = $this->checkDate($to) ;
        $piva = $this->validatePI($piva);

        parent::__construct("Fatture_Clienti_".$piva);

        $fatture = parent::addElement("ns1:Fatture");

        parent::addElement("ns1:Richiesta","FATT",$fatture);

        $pivalist = parent::addElement("ns1:ElencoPiva",null,$fatture);
        parent::addElement("ns1:Piva",$piva,$pivalist);

        parent::addElement("ns1:TipoRicerca","PUNTUALE", $fatture);

        $fattureEmesse = parent::addElement("ns1:FattureEmesse",null, $fatture);

        $DataEmissione = parent::addElement("ns1:DataEmissione",null, $fattureEmesse);
        parent::addElement("ns1:Da",$from, $DataEmissione);
        parent::addElement("ns1:A",$to, $DataEmissione);        


        $flusso = parent::addElement("ns1:Flusso",null, $fattureEmesse);
        parent::addElement("ns1:Tutte","ALL", $flusso);  
        
        parent::addElement("ns1:Ruolo","CEDENTE", $fattureEmesse);

        $this->tofile();
    }

}
Class FattureFornitoriXml extends XmlBuilder {
    
    use XmlUtils;

    public function __construct(string $from,string $to,string $piva) {

        $from = $this->checkDate($from) ; // possono lanciare eccezioni
        $to   = $this->checkDate($to) ;
        $piva = $this->validatePI($piva);

        parent::__construct("Fatture_fornitori_".$piva);

        $fatture = parent::addElement("ns1:Fatture");

        parent::addElement("ns1:Richiesta","FATT",$fatture);

        $pivalist = parent::addElement("ns1:ElencoPiva",null,$fatture);
        parent::addElement("ns1:Piva",$piva,$pivalist);

        parent::addElement("ns1:TipoRicerca","PUNTUALE", $fatture);

        $FattureRicevute = parent::addElement("ns1:FattureRicevute",null, $fatture);

        $DataRicezione = parent::addElement("ns1:DataRicezione",null, $FattureRicevute);
        parent::addElement("ns1:Da",$from, $DataRicezione);
        parent::addElement("ns1:A",$to, $DataRicezione);        


        $flusso = parent::addElement("ns1:Flusso",null, $FattureRicevute);
        parent::addElement("ns1:Tutte","ALL", $flusso);  
        
        parent::addElement("ns1:Ruolo","CESSIONARIO", $FattureRicevute);

        $this->tofile();
    }

}
/**
 * Estendo exception per una particolare situazione: nel caso in una alcuni codici fiscali siano errati
 * il programma, non genera un eccezione distruttiva,ma continua la sua esecuzione, salvandosi in una lista
 * quali siano i codici fiscali che non sono conformi,al termine, si controlla se ci sia qualche codice fiscale
 * che non sia valido, in quel'caso si genera la FiscalCodeException che genera la lista dei Cf non validi.
 */
class FiscalCodeException extends Exception {
    private array $errlist;
    public function __construct(string $message,array $errlist, $code = 0, Exception $previous = null) {
        parent::__construct($message, $code, $previous);
        $this->errlist = $errlist;
    }    
    public function getErrorList():array {
        return $this->errlist;
    }      
}
Class VerificaAnagraficaXml extends XmlBuilder {

    private bool $health;    // true se tutti i codici fiscali sono corretti
    private array $errorlst; // lista di codici fiscali non conformi

    public function __construct(array $cflist) {

        if(is_null($cflist))  throw new Exception("Lista nulla");

        parent::__construct("Verifica_anagrafica");

        $this->health = true;      //Assumiamo che per ora tutti i Cf sono validi
        $this->errorlst = array(); //Lista vuota

        $anagrafica = parent::addElement("ns1:Anagrafica");

        parent::addElement("ns1:Richiesta","VER_ANAG",$anagrafica);
        parent::addElement("ns1:TipoSoggetto","CF",$anagrafica);
        $ElencoSoggetti = parent::addElement("ns1:ElencoSoggetti",null,$anagrafica);

        foreach($cflist as $cf) { // Per ogni elemento dell'array, ovvero un CF

            $cf = trim($cf); // Rimuovo gli spazi
            if(strlen($cf)==16)  parent::addElement("ns1:Soggetto",$cf,$ElencoSoggetti);
            else {
                // Vedi FiscalCodeException
                $this->health=false;
                array_push($this->errorlst,$cf);
            }
        }
        
        $this->tofile();
    }
    public function checkHealth():bool { return $this->health; }

    public function getCfErrorLst():array { return $this->errorlst; }
}
?>
