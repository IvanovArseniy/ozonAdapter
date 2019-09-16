<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Log;

class GetProductInfos extends Command
{
    protected $name        = 'getProductInfos';
    protected $description = 'getProductInfos worker';

    public function handle()
    {
        $orders = [
            "35826892-0001",
            "35027126-0001",
            "30574029-0011",
            "08428035-0001",
            "08428035-0001",
            "26439733-0004",
            "35684483-0002",
            "05089304-0020",
            "19005180-0013",
            "33785345-0003",
            "28540167-0019",
            "28540167-0019",
            "37743323-0001",
            "24242423-0044",
            "09509922-0230",
            "26433362-0020",
            "35913694-0001",
            "18200057-0022",
            "03414430-0005",
            "03414430-0005",
            "33157074-0001",
            "33157074-0001",
            "37780288-0002",
            "04236427-0015",
            "37301430-0003",
            "15470481-0003",
            "15470481-0003",
            "24309343-0093",
            "13662785-0024",
            "37767843-0003",
            "37562452-0002",
            "37562452-0002",
            "31778177-0017",
            "19824133-0003",
            "37612218-0001",
            "33772380-0006",
            "35156277-0003",
            "35156277-0003",
            "35156277-0003",
            "37825498-0002",
            "04788645-0005",
            "04788645-0005",
            "26519877-0003",
            "14218777-0126",
            "14218777-0126",
            "14218777-0126",
            "34352538-0002",
            "37257276-0001",
            "04949200-0075",
            "32219128-0003",
            "32219128-0003",
            "18177284-0016",
            "33598016-0011",
            "33598016-0011",
            "33598016-0011",
            "33598016-0011",
            "37694237-0003",
            "37694237-0003",
            "37694237-0003",
            "37694237-0003",
            "31088642-0038",
            "31088642-0038",
            "07811545-0008",
            "14921355-0085",
            "33703078-0130",
            "15857226-0013",
            "15857226-0013",
            "29749326-0008",
            "37047634-0002",
            "22517151-0015",
            "22517151-0015",
            "33773484-0024",
            "33773484-0024",
            "33773484-0024",
            "36787969-0002",
            "36185245-0002",
            "30918603-0008",
            "23212768-0006",
            "37865483-0001",
            "24864278-0021",
            "30792041-0009",
            "30792041-0009",
            "03345462-0047",
            "33578758-0002",
            "07724094-0023",
            "35904958-0014",
            "35904958-0015",
            "37376586-0003",
            "09758525-0095",
            "37143656-0007",
            "09116518-0005",
            "07720238-0379",
            "37882424-0002",
            "35979916-0007",
            "29893883-0012",
            "32095852-0006",
            "06152047-0049",
            "37861819-0001",
            "32522828-0002",
            "37807540-0001",
            "37807540-0001",
            "37830668-0002",
            "37741623-0001",
            "23666670-0027",
            "05193710-0078",
            "35411369-0010",
            "34613694-0040",
            "37370538-0004",
            "37370538-0004",
            "34378958-0010",
            "08474714-0020",
            "08474714-0020",
            "15186319-0031",
            "15186319-0031",
            "15186319-0031",
            "25142869-0007",
            "37891013-0003",
            "13243361-0140",
            "29879568-0008",
            "29879568-0008",
            "29288361-0068",
            "37707603-0004",
            "37707603-0004",
            "13530276-0012",
            "36567825-0008",
            "35775604-0007",
            "37074853-0002",
            "29623676-0020",
            "37791199-0002",
            "11625787-0040",
            "11625787-0040",
            "35156913-0011",
            "30748454-0010",
            "01766466-0036",
            "29979069-0019",
            "31054434-0005",
            "28141096-0008",
            "16172010-0009",
            "16172010-0009",
            "37766484-0004",
            "35680831-0026",
            "37898721-0001",
            "37898721-0001",
            "13866917-0012",
            "13535403-0044",
            "37884438-0001",
            "37092152-0008",
            "37092152-0008",
            "29675354-0014",
            "29675354-0014",
            "37901190-0001",
            "37901424-0001",
            "34371234-0008",
            "32507443-0023",
            "36449043-0002",
            "31969802-0009",
            "04758977-0084",
            "20115573-0066",
            "30516906-0003",
            "18332780-0023",
            "18332780-0023",
            "22851616-0040",
            "22851616-0040",
            "28155383-0012",
            "24254746-0028",
            "04107912-0361",
            "28592466-0006",
            "28592466-0006",
            "34065886-0002",
            "37574564-0004",
            "37574564-0004",
            "34711943-0001",
            "31212074-0009",
            "35872457-0006",
            "26390307-0008",
            "37903870-0001",
            "37903870-0001",
            "37903870-0001",
            "37903870-0001",
            "37904279-0001",
            "37904279-0001",
            "28242608-0040",
            "17166415-0013",
            "37215138-0004",
            "09932238-0045",
            "34528885-0008",
            "20117611-0012",
            "31182213-0004",
            "29235592-0002",
            "29595518-0004",
            "16915658-0011",
            "03812727-0011",
            "29581485-0009",
            "29581485-0009",
            "31680175-0004",
            "37906740-0001",
            "25345745-0002",
            "34878236-0004",
            "31994692-0008",
            "31994692-0008",
            "35069955-0006",
            "36346200-0002",
            "35225950-0004",
            "37895274-0003",
            "37895274-0003",
            "37895274-0003",
            "37895274-0003",
            "35682390-0003",
            "32873988-0005",
            "37501493-0003",
            "33034105-0009",
            "33034105-0009",
            "37455645-0002",
            "37717836-0003",
            "37717836-0003",
            "37907263-0001",
            "26280982-0009",
            "35549283-0007",
            "37344016-0001",
            "30571010-0027",
            "34799438-0002",
            "34799438-0002",
            "34799438-0002",
            "34799438-0002",
            "34799438-0002",
            "34799438-0002",
            "31773939-0010",
            "34009176-0019",
            "37161626-0008",
            "14322875-0013",
            "14322875-0013",
            "34092608-0003",
            "36898612-0003",
            "37910793-0001",
            "36829357-0011",
            "32080522-0018",
            "31224598-0026",
            "31224598-0026",
            "31224598-0026",
            "31224598-0026",
            "37905734-0003",
            "37905734-0003",
            "36879414-0006",
            "37437751-0001",
            "34874539-0019",
            "36043158-0016",
            "37879907-0003",
            "33602825-0008",
            "37911640-0001",
            "29356475-0007",
            "37912222-0001",
            "36091216-0008",
            "15339865-0015",
            "37891623-0001",
            "30221986-0010",
            "33260776-0001",
            "12861404-0009",
            "12861404-0009",
            "33583577-0007",
            "37802531-0003",
            "29914740-0005",
            "37547816-0003",
            "37908869-0001",
            "11356134-0052",
            "36699578-0004",
            "29885270-0003",
            "37889739-0003",
            "11855063-0016",
            "37908597-0001",
            "24216467-0006",
            "29775343-0005",
            "23586139-0010",
            "25812218-0035",
            "15162125-0026",
            "35350197-0003",
            "35350197-0003",
            "37913595-0001",
            "24116243-0008",
            "32913439-0005",
            "37701393-0001",
            "18043529-0014",
            "18043529-0014",
            "13165089-0008",
            "37194737-0002",
            "37914482-0001",
            "37913977-0001",
            "36113373-0015",
            "36331676-0004",
            "37737747-0003",
            "37903441-0001",
            "30373006-0018",
            "30373006-0018",
            "36982158-0003",
            "02609225-0223",
            "02609225-0223",
            "29142264-0002",
            "29142264-0002",
            "32515891-0021",
            "33656832-0001",
            "35597240-0007",
            "37832914-0002",
            "08745334-0021",
            "35211497-0004",
            "05730644-0070",
            "36708257-0003",
            "12997275-0030",
            "35627719-0005",
            "36637321-0002",
            "29712480-0036",
            "36781392-0003",
            "22095715-0083",
            "22095715-0083",
            "22095715-0083",
            "22095715-0083",
            "28271489-0002",
            "28271489-0002",
            "33887972-0007",
            "36806235-0001",
            "10959686-0026",
            "35954366-0006",
            "16665178-0014",
            "03401844-0047",
            "31668497-0004",
            "34683605-0002",
            "37063754-0002",
            "35386887-0009",
            "28572909-0017",
            "33839114-0001",
            "37254424-0002",
            "08567659-0010",
            "37916935-0001",
            "33123646-0005",
            "33123646-0005",
            "31537179-0038",
            "14031021-0008",
            "14031021-0008",
            "37872724-0001",
            "32253567-0003",
            "34552724-0005",
            "37767213-0002",
            "18733387-0055",
            "24785298-0007",
            "03825327-0010",
            "05424281-0148",
            "10263506-0004",
            "33941952-0006",
            "37914938-0001",
            "37917752-0001",
            "25010438-0079",
            "25010438-0079",
            "25010438-0079",
            "32022144-0006",
            "36327945-0003",
            "37914605-0002",
            "11238534-0021",
            "34003807-0004",
            "34575227-0009",
            "33958953-0009",
            "36551126-0006",
            "07862989-0115",
            "08897546-0010",
            "37702598-0001",
            "28059749-0018",
            "30963416-0004",
            "36027989-0001",
            "33219511-0007",
            "26485515-0010",
            "36751362-0002",
            "37918639-0001",
            "03946816-0100",
            "36938375-0002",
            "26717323-0006",
            "36155119-0002",
            "07794828-0001",
            "37789936-0002",
            "37906548-0001",
            "29334471-0021",
            "31284630-0006",
            "35524927-0008",
            "36846319-0001",
            "37919227-0001",
            "37919241-0001",
            "29630738-0025",
            "34007767-0012",
            "37824984-0003",
            "37919343-0001",
            "34093334-0013",
            "37918926-0002",
            "10524577-0025",
            "30767150-0016",
            "34809207-0004",
            "31563522-0006",
            "32596823-0004",
            "37919806-0002",
            "29510198-0007",
            "29534722-0008",
            "35094909-0011",
            "35771752-0006",
            "37918338-0001",
            "20030314-0027",
            "30758479-0002",
            "30758479-0002",
            "34119780-0007",
            "36701999-0001",
            "13117261-0336",
            "34766627-0009",
            "35855180-0011",
            "35855180-0011",
            "06215694-0084",
            "33529968-0007",
            "32569129-0008",
            "32569129-0008",
            "37338811-0002",
            "37785819-0003",
            "37086463-0003",
            "30923300-0016",
            "25239725-0017",
            "37030563-0001",
            "35636506-0006",
            "23134667-0012",
            "33703430-0005",
            "33703430-0005",
            "37921517-0003",
            "25149704-0029",
            "31180715-0005",
            "30565193-0007",
            "05316243-0139",
            "30787550-0002",
            "30787550-0002",
            "37638123-0002",
            "06143565-0112",
            "32787142-0002",
            "03874673-0001",
            "03874673-0001",
            "37877520-0003",
            "18438933-0004",
            "30927567-0031",
            "07899835-0009",
            "24077565-0003",
            "36939611-0009",
            "37923657-0001",
            "36627694-0011",
            "37200984-0003",
            "32079862-0006",
            "34536744-0004",
            "30383436-0036",
            "37924030-0001",
            "37924111-0002",
            "37924169-0001",
            "35291648-0007",
            "24082016-0027",
            "31800130-0015",
            "37320978-0003",
            "07241047-0151",
            "37918549-0001",
            "17907114-0004",
            "17907114-0004",
            "17907114-0004",
            "37924644-0001",
            "37336355-0004",
            "31882341-0030",
            "31882341-0030",
            "32357677-0001",
            "37048786-0003",
            "33665281-0009",
            "31599750-0011",
            "06676955-0051",
            "07181036-0018",
            "12610264-0017",
            "30666277-0007",
            "37925076-0001",
            "37622151-0001",
            "14470707-0029",
            "37917861-0001",
            "37917861-0001",
            "37925194-0001",
            "09385295-0012",
            "37925452-0001",
            "37925786-0001",
            "37925786-0001",
            "23348914-0008",
            "37863012-0003",
            "12406987-0004",
            "07891376-0028",
            "37788154-0004",
            "14731216-0019",
            "34629268-0001",
            "35198877-0004",
            "35972815-0001",
            "26089516-0029",
            "33515012-0001",
            "19414512-0003",
            "19414512-0003",
            "28783733-0004",
            "37491722-0002",
            "37927460-0001",
            "06432471-0038",
            "06432471-0038",
            "31830413-0013",
            "35497132-0007",
            "30475679-0002",
            "37928045-0001",
            "34658166-0003",
            "37426571-0004",
            "11749362-0017",
            "35825961-0009",
            "37750094-0002",
            "37833251-0001",
            "37928750-0001",
            "37166855-0004",
            "37815758-0002",
            "31930859-0004",
            "37602328-0002",
            "37929500-0001",
            "37929500-0001",
            "21923748-0006",
            "08474432-0045",
            "36588751-0003",
            "18927966-0102",
            "37930265-0001",
            "37509690-0002",
            "37509690-0002",
            "32757121-0015",
            "32757121-0015",
            "13199948-0011",
            "35136931-0005",
            "35136931-0005",
            "07662958-0001",
            "17166935-0003",
            "32951943-0004",
            "36680983-0005",
            "37930709-0001",
            "14859692-0022",
            "31130022-0001",
            "06538830-0011",
            "31003761-0001",
            "15161175-0010",
            "19176398-0011",
            "19176398-0011",
            "24384754-0108",
            "36665625-0001",
            "37923790-0001",
            "35432861-0001",
            "06868627-0014",
            "37352204-0001",
            "20928550-0002",
            "32653658-0004",
            "37932393-0001",
            "37326554-0002",
            "37883926-0001",
            "37932864-0001",
            "32161854-0007",
            "36993004-0007",
            "36993004-0007",
            "32979795-0004",
            "37636825-0002",
            "37931156-0001",
            "37906381-0002",
            "05082037-0013",
            "28403988-0018",
            "06988464-0004",
            "24603694-0090",
            "29935033-0017",
            "30074794-0013",
            "34763834-0004",
            "37700074-0001",
            "37933661-0001",
            "23320701-0004",
            "23320701-0004",
            "29230280-0012",
            "29273473-0014",
            "36039861-0004",
            "36682534-0005",
            "37700234-0001",
            "34448152-0007",
            "37611760-0001",
            "37352623-0002",
            "10382590-0034",
            "37934353-0001",
            "09321178-0015",
            "35454839-0007",
            "35454839-0007",
            "25606074-0006",
            "37383534-0007",
            "37677812-0002",
            "33634912-0007",
            "37152134-0003",
            "37152134-0003",
            "29661514-0026",
            "18008610-0026",
            "36032320-0003",
            "37432659-0001",
            "37711080-0004",
            "32305546-0001",
            "36374204-0005",
            "07906733-0066",
            "37316319-0004",
            "33196992-0001",
            "01569903-0014",
            "34645085-0004",
            "37932026-0001",
            "31354709-0001",
            "36839533-0003",
            "34487907-0004",
            "35524927-0009",
            "37932269-0002",
            "29528990-0004",
            "37919392-0001",
            "36506527-0006",
            "37916497-0002",
            "11794298-0057",
            "21811747-0046",
            "34315843-0007",
            "37927202-0001",
            "02251904-0006",
            "02251904-0006",
            "24190972-0021",
            "29260429-0065",
            "29260429-0065",
            "37937027-0001",
            "18462473-0012",
            "18462473-0012",
            "36515993-0007",
            "30680512-0025",
            "34160462-0001",
            "34972579-0006",
            "35885162-0004",
            "37927839-0002",
            "37935233-0002",
            "17663415-0013",
            "03257132-0115",
            "17953030-0041",
            "17284760-0007",
            "37275232-0002",
            "35471056-0001",
            "34575540-0005",
            "16919752-0007",
            "29621273-0009",
            "29621273-0009",
            "37520310-0002",
            "37424481-0008",
            "21331077-0040",
            "33056280-0003",
            "04078621-0086",
            "04078621-0086",
            "14014169-0011",
            "14014169-0011",
            "37934836-0001",
            "03903335-0078",
            "13039010-0019",
            "29210025-0004",
            "15842330-0017",
            "37939564-0002",
            "35085370-0005",
            "37939955-0001",
            "08663108-0023",
            "20528101-0003",
            "31848239-0002",
            "33866161-0006",
            "19384685-0019",
            "15665720-0040",
            "02693013-0268",
            "02693013-0268",
            "09074243-0012",
            "34230425-0001",
            "35754506-0004",
            "35754506-0004",
            "08435571-0048",
            "12535870-0014",
            "12535870-0014",
            "33546512-0004",
            "34261353-0008",
            "34261353-0008",
            "30421933-0014",
            "08961608-0056",
            "35559800-0002",
            "34874539-0020",
            "10241388-0005",
            "10241388-0005",
            "19144684-0009",
            "36676516-0003",
            "35545666-0009",
            "30277696-0007",
            "37940884-0003",
            "37942169-0001",
            "26645301-0011",
            "19968217-0104",
            "37296511-0007",
            "37943062-0001",
            "03023196-0007",
            "37774627-0001",
            "34804484-0010",
            "35237739-0003",
            "36772860-0002",
            "28948585-0005",
            "36573850-0003",
            "36573850-0003",
            "37541010-0001",
            "34583739-0006",
            "08541904-0196",
            "09294008-0033",
            "37946655-0001",
            "37677962-0002",
            "36654367-0003",
            "36654367-0003",
            "02701199-0071",
            "21084388-0012",
            "36494221-0001",
            "17690609-0014",
            "07101475-0032",
            "32698864-0001",
            "36085668-0003",
            "32180110-0001",
            "35769399-0004",
            "37948084-0001",
            "34260325-0001",
            "25025012-0004",
            "30432604-0004",
            "23074052-0013",
            "37802832-0004",
            "37949156-0002",
            "22214895-0011",
            "18249829-0012",
            "31580183-0011",
            "31580183-0011",
            "31940899-0030",
            "06314686-0021",
            "15703955-0023",
            "27859621-0022",
            "35509533-0005",
            "04498560-0060",
            "37789354-0003",
            "34717770-0006",
            "35119926-0008",
            "37568748-0004",
            "37950308-0001",
            "08337815-0292",
            "31899206-0001",
            "35738091-0008",
            "35387921-0004",
            "36932962-0002",
            "36932962-0002",
            "36932962-0002",
            "36039865-0007",
            "05496611-0025",
            "17745653-0011",
            "37930911-0002",
            "30837464-0004",
            "18921629-0007",
            "13504193-0018",
            "29313821-0030",
            "17344143-0005",
            "03386225-0078",
            "35377893-0006",
            "30805985-0008",
            "33438858-0001",
            "37771380-0001",
            "37951851-0001",
            "17222441-0002",
            "14099508-0040",
            "23978193-0012",
            "36650693-0002",
            "37951198-0001",
            "37952147-0001",
            "33613202-0001",
            "37949557-0001",
            "32357541-0031",
            "28540146-0008",
            "04697088-0022",
            "03248650-0030",
            "14298643-0005",
            "34349287-0017",
            "30179020-0005",
            "35514377-0010",
            "36338919-0003",
            "36619408-0005",
            "06856586-0067",
            "06856586-0067",
            "06856586-0067",
            "24915328-0004",
            "37952057-0005",
            "05047950-0009",
            "05047950-0009",
            "33835510-0002",
            "37953495-0001",
            "26100893-0006",
            "37756624-0001",
            "20853062-0018",
            "37953884-0001",
            "28496854-0004",
            "36848015-0002",
            "37885412-0001",
            "15888956-0006",
            "33606022-0027",
            "34390423-0003",
            "34505492-0017",
            "14791574-0007",
            "37954814-0001",
            "33133756-0002",
            "29914984-0013",
            "36663116-0010",
            "37954764-0001",
            "35778133-0002",
            "35778133-0002",
            "37955246-0001",
            "11254949-0013",
            "20394214-0013",
            "34203675-0013",
            "34203675-0013",
            "37197324-0001",
            "09673874-0087",
            "09673874-0087",
            "09673874-0087",
            "37955365-0001",
            "03208563-0079",
            "37955725-0001",
            "17114386-0087",
            "17114386-0087",
            "26694380-0116",
            "34263853-0001",
            "34263853-0001",
            "36346182-0002",
            "36346182-0002",
            "36950100-0002",
            "05173715-0015",
            "18022442-0040",
            "26984816-0014",
            "29127472-0024",
            "37955905-0001",
            "37955905-0001",
            "04645736-0033",
            "11221378-0079",
            "15255170-0016",
            "29691569-0011",
            "28910220-0001",
            "11218849-0009",
            "28892814-0023",
            "30763687-0008",
            "34162014-0001",
            "37956542-0001",
            "21796543-0003",
            "21796543-0003",
            "37956923-0001",
            "12964611-0109",
            "28910220-0002",
            "31848239-0004",
            "35550349-0003",
            "11444226-0001",
            "17923772-0014",
            "37682805-0002",
            "37739170-0001",
            "04411446-0055",
            "37222212-0006",
            "29562272-0010",
            "29562272-0010",
            "29562272-0010",
            "34942630-0002",
            "34942630-0002",
            "37765746-0001",
            "03852324-0105",
            "37837280-0001",
            "21158954-0021",
            "32588805-0016",
            "37958074-0001",
            "34995672-0006",
            "03600796-0031",
            "35483971-0002",
            "37247426-0002",
            "31657752-0003",
            "37247426-0003",
            "37767112-0004",
            "03663061-0022",
            "37958926-0001",
            "30120686-0018",
            "02679865-0007",
            "12368052-0063",
            "26385886-0003",
            "15188139-0012",
            "22821100-0115",
            "37959189-0001",
            "31982751-0026",
            "37959051-0001",
            "10721469-0004",
            "12293808-0062",
            "33632676-0006",
            "04410843-0004",
            "30387974-0014",
            "32670807-0009",
            "37915328-0002",
            "23660323-0006",
            "25012793-0011",
            "31632306-0069",
            "18089277-0027",
            "32773938-0005",
            "35017373-0003",
            "37959937-0001",
            "37935586-0001",
            "37947524-0001",
            "37960163-0001",
            "29576100-0005",
            "37960348-0001",
            "14912958-0136",
            "26648267-0014",
            "36921917-0002",
            "37512201-0002",
            "37512201-0002",
            "37551303-0001",
            "24737895-0006",
            "09387288-0228",
            "15102412-0017",
            "19344410-0005",
            "25141801-0058",
            "25866096-0017",
            "36437283-0001",
            "08770314-0023",
            "31479576-0003",
            "36118730-0008",
            "37960649-0001",
            "37960738-0001",
            "19664034-0016",
            "31143740-0003",
            "37960999-0001",
            "07544552-0033",
            "24710845-0029",
            "24710845-0029",
            "37960011-0002",
            "37961028-0001",
            "31825214-0002",
            "36755982-0003",
            "37144424-0002",
            "37144424-0002",
            "37938474-0002",
            "37957000-0001",
            "07505142-0276",
            "37961063-0001",
            "24227591-0007",
            "33226610-0001",
            "33275007-0010",
            "37962010-0001",
            "20359546-0018",
            "33537703-0007",
            "37393249-0003",
            "37508926-0005",
            "27900674-0014",
            "37962322-0001",
            "04636421-0043",
            "29908023-0002",
            "28652605-0014",
            "28652605-0014",
            "31319596-0014",
            "37925022-0002",
            "21379335-0012",
            "21379335-0012",
            "33487270-0009",
            "35661915-0012",
            "35652241-0002",
            "36327887-0005",
            "30087990-0008",
            "37940673-0001",
            "37962416-0001",
            "37334967-0004",
            "37962408-0003",
            "30473217-0022",
            "30473217-0022",
            "26313743-0004",
            "30499063-0005",
            "37963960-0001",
            "05449978-0021",
            "06406740-0118",
            "37344512-0003",
            "30692533-0004",
            "37278971-0004",
            "26507802-0003",
            "03737253-0017",
            "37323109-0001",
            "37673685-0002",
            "29889038-0013",
            "29889038-0013",
            "37963771-0001",
            "01514654-0105",
            "26175023-0018",
            "26175023-0018",
            "31661602-0012",
            "35175727-0004",
            "37964480-0001",
            "30693353-0004",
            "17786511-0015",
            "32978658-0007",
            "24182712-0008",
            "37535000-0003",
            "14069575-0001",
            "14069575-0001",
            "22738779-0008",
            "12300998-0093",
            "35478324-0003",
            "35624895-0002",
            "36455705-0010",
            "37965403-0001",
            "37965376-0001",
            "04441041-0017",
            "04670348-0052",
            "32560000-0002",
            "08104242-0009",
            "37357629-0006",
            "37949253-0001"
        ];


        $dates = [
            // '2019-08-16',
            // '2019-08-17',
            // '2019-08-18',
            // '2019-08-19',
            // '2019-08-20',
            // '2019-08-21',
            // '2019-08-22',
            // '2019-08-23',
            // '2019-08-24',


            // '2019-08-25',
            // '2019-08-26',
            // '2019-08-27',
            // '2019-08-28',
            // '2019-08-29',
            // '2019-08-30',
            // '2019-08-31',
            // '2019-09-01',
            // '2019-09-02',
            // '2019-09-03',


            '2019-09-04',
            '2019-09-05',
            '2019-09-06',
            '2019-09-07',
            '2019-09-08',
            '2019-09-09',
            '2019-09-10',
            '2019-09-11',
            '2019-09-12',
            '2019-09-13'
        ];

        $ordersFull = [];
        $i = 0;
        foreach ($orders as $key => $orderNr) {
            $orderDb = app('db')->connection('mysql')->table('orders')
                ->where('ozon_order_nr',  $orderNr)
                ->first();
            if (!$orderDb) {
                continue;
            }
            
            $ordersFull[$i] = [
                'ozon_order_nr' => $orderNr,
                'ozon_order_id' => $orderDb->ozon_order_id,
                'tran_uuid' => null,
                'approve_request' => '',
                'approve_request_date' => '',
                'approve_response' => null,
                'approve_response_date' => null,
            ];
            $i++;
        }

        $lines = [];
        foreach ($dates as $key => $date) {

            $handle = fopen('/var/www/ozon/storage/logs/laravel-' . $date . '.log', 'r');
            while (($buffer = fgets($handle)) !== false) {
                if (strpos($buffer, "Approve ozon order") !== false) {
                    array_push($lines, $buffer);
                }
            }
        }

        echo count($lines);


        foreach ($lines as $key => $buffer) {

                $i = 0;
                foreach ($orders as $key => $order) {
                    if (is_null($ordersFull[$i]['approve_response'])) {
                        if (strpos($buffer, "Approve ozon order:" . $ordersFull[$i]['ozon_order_id']) !== false) {
                            $ordersFull[$i]['tran_uuid'] = substr($buffer, 39, 13);
                            $ordersFull[$i]['approve_request'] = $buffer;
                            $ordersFull[$i]['approve_request_date'] = substr($buffer, 1, 19);
                        }
                        if (!is_null($ordersFull[$i]['tran_uuid']) && strpos($buffer, $ordersFull[$i]['tran_uuid']) !== false && strpos($buffer, "Approve ozon order result:") !== false) {
                            if (strpos($buffer, "success") !== false) {
                                $ordersFull[$i]['approve_response'] = $buffer;
                                $ordersFull[$i]['approve_response_date'] = substr($buffer, 1, 19);
                            }
                            else {
                                $ordersFull[$i]['tran_uuid'] = null;
                            }
                        }
                    }
                    $i++;
                }
        }

        $fp = fopen('/var/www/ozon/storage/logs/file.csv', 'w');

        foreach ($ordersFull as $fields) {
            fputcsv($fp, $fields);
        }

        fclose($fp);


        echo "ready!";
        return;
    }




    //$result = [];
    //         $exit = false;
    //         $i = 0;
    //         $fileDate = null;
    //         $order = null;
    //         $handle = null;
    //         $debug = true;
    //         $line = null;
    //         $line1 = "xxxyyyzzzz";
    //         $line2 = "";
    //         $element = [];
    //         $uuid = null;
    //         $good = false;
    //         while (!$exit) {
    //             if ($i < count($orders)) {
    //                 $order = $orders[$i];
    //                 $orderNr = $order[0];
    //                 $date = $order[1];
    //                 $orderDate = new \DateTime($date);
    //                 $orderDb = app('db')->connection('mysql')->table('orders')
    //                     ->where('ozon_order_nr',  $orderNr)
    //                     ->first();
    
    //                 if (!$orderDb) {
    //                     continue;
    //                 }
    //             }
    
    //             if ($fileDate != $orderDate) {
    //                 if (!is_null($handle)) {
    //                     fclose($handle);
    //                 }
    //                 $fileDate = $orderDate;
    //                 $handle = fopen('/var/www/ozon/storage/logs/laravel-' . $fileDate->format('Y-m-d') . '.log', 'r');
    //             }
    
    //             while (($buffer = fgets($handle)) !== false) {
    //                 if($i == count($orders)) {
    //                     break;
    //                 }
    //                 if (strpos($buffer, $line1) !== false && $good) {
    //                     $good = false;
    //                     echo $buffer;
    //                     $line2 = substr($buffer, 1, 19);
    //                     $line3 = $buffer;
    //                     $element['date'] = $line2;
    //                     $element['response'] = $line3;
    
    //                     if($i == count($orders)) {
    //                         break;
    //                     }
    //                 }
    //                 if (strpos($buffer, "Approve ozon order:" . $orderDb->ozon_order_id) !== false) {
    //                     array_push($result, $element);
    //                     $element = [];
    //                     $good = true;
    //                     echo $buffer;
    //                     $line1 = $buffer;
    //                     $line1 = substr($line1, 39, 13);
    // //                    array_push($result, [$orderNr, $line1]);
    //                     $element['orderNr'] = $orderNr;
    
    //                     $i++;
    
    //                     if ($i < count($orders)) {
    //                         $order = $orders[$i];
    //                         $orderNr = $order[0];
    //                         $date = $order[1];
    //                         $orderDate = new \DateTime($date);
    //                         $orderDb = app('db')->connection('mysql')->table('orders')
    //                         ->where('ozon_order_nr',  $orderNr)
    //                         ->first();
    
    //                         if (!$orderDb) {
    //                             continue;
    //                         }
    //                     }
    
    //                     if ($fileDate != $orderDate) {
    //                         break;
    //                     }
    //                 } 
    //             }     
    
    
    //             if($i == count($orders)) {
    //                 $exit = true;
    //                 fclose($handle);
    //             }
    //         }
}
