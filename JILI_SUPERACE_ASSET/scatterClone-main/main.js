
let slots = [[c1Slot1, c1Slot2, c1Slot3, c1Slot4],
             [c2Slot1, c2Slot2, c2Slot3, c2Slot4],
             [c3Slot1, c3Slot2, c3Slot3, c3Slot4],
             [c4Slot1, c4Slot2, c4Slot3, c4Slot4],
             [c5Slot1, c5Slot2, c5Slot3, c5Slot4]];
const slotsAnimations = [
  ['c1Slot1Roll', 'c1Slot2Roll', 'c1Slot3Roll', 'c1Slot4Roll'],
  ['c2Slot1Roll', 'c2Slot2Roll', 'c2Slot3Roll', 'c2Slot4Roll'],
  ['c3Slot1Roll', 'c3Slot2Roll', 'c3Slot3Roll', 'c3Slot4Roll'],
  ['c4Slot1Roll', 'c4Slot2Roll', 'c4Slot3Roll', 'c4Slot4Roll'],
  ['c5Slot1Roll', 'c5Slot2Roll', 'c5Slot3Roll', 'c5Slot4Roll']];
const slotsReRollAnimations = [
  ['c1Slot1ReRoll', 'c1Slot2ReRoll', 'c1Slot3ReRoll', 'c1Slot4ReRoll'],
  ['c2Slot1ReRoll', 'c2Slot2ReRoll', 'c2Slot3ReRoll', 'c2Slot4ReRoll'],
  ['c3Slot1ReRoll', 'c3Slot2ReRoll', 'c3Slot3ReRoll', 'c3Slot4ReRoll'],
  ['c4Slot1ReRoll', 'c4Slot2ReRoll', 'c4Slot3ReRoll', 'c4Slot4ReRoll'],
  ['c5Slot1ReRoll', 'c5Slot2ReRoll', 'c5Slot3ReRoll', 'c5Slot4ReRoll']];

const slotsPosition = ['slot1Position','slot2Position','slot3Position','slot4Position','slot5Position',
                        'slot6Position','slot7Position','slot8Position','slot9Position','slot10Position',
                        'slot11Position','slot12Position','slot13Position','slot14Position','slot15Position',
                        'slot16Position','slot17Position','slot18Position','slot19Position','slot20Position',];

const red_wildCards = [
  [[c1R1W1, c1R1W2, c1R1W3],[c1R2W1, c1R2W2, c1R2W3],[c1R3W1, c1R3W2, c1R3W3],[c1R4W1, c1R4W2, c1R4W3]],
  [[c2R1W1, c2R1W2, c2R1W3],[c2R2W1, c2R2W2, c2R2W3],[c2R3W1, c2R3W2, c2R3W3],[c2R4W1, c2R4W2, c2R4W3]],
  [[c3R1W1, c3R1W2, c3R1W3],[c3R2W1, c3R2W2, c3R2W3],[c3R3W1, c3R3W2, c3R3W3],[c3R4W1, c3R4W2, c3R4W3]],
  [[c4R1W1, c4R1W2, c4R1W3],[c4R2W1, c4R2W2, c4R2W3],[c4R3W1, c4R3W2, c4R3W3],[c4R4W1, c4R4W2, c4R4W3]],
  [[c5R1W1, c5R1W2, c5R1W3],[c5R2W1, c5R2W2, c5R2W3],[c5R3W1, c5R3W2, c5R3W3],[c5R4W1, c5R4W2, c5R4W3]]];

let rollc1L1, rollc1L2, rollc1L3, rollc1L4,
    rollc2L1, rollc2L2, rollc2L3, rollc2L4,
    rollc3L1, rollc3L2, rollc3L3, rollc3L4,
    rollc4L1, rollc4L2, rollc4L3, rollc4L4,
    rollc5L1, rollc5L2, rollc5L3, rollc5L4;
  
let cardNo_c1L1, cardNo_c1L2, cardNo_c1L3, cardNo_c1L4,
    cardNo_c2L1, cardNo_c2L2, cardNo_c2L3, cardNo_c2L4,
    cardNo_c3L1, cardNo_c3L2, cardNo_c3L3, cardNo_c3L4,
    cardNo_c4L1, cardNo_c4L2, cardNo_c4L3, cardNo_c4L4,
    cardNo_c5L1, cardNo_c5L2, cardNo_c5L3, cardNo_c5L4;
  
let slotsRan = [
    [rollc1L1, rollc1L2, rollc1L3, rollc1L4],
    [rollc2L1, rollc2L2, rollc2L3, rollc2L4],
    [rollc3L1, rollc3L2, rollc3L3, rollc3L4],
    [rollc4L1, rollc4L2, rollc4L3, rollc4L4],
    [rollc5L1, rollc5L2, rollc5L3, rollc5L4]];
    
let slotsCardNo = [
    [cardNo_c1L1, cardNo_c1L2, cardNo_c1L3, cardNo_c1L4],
    [cardNo_c2L1, cardNo_c2L2, cardNo_c2L3, cardNo_c2L4],
    [cardNo_c3L1, cardNo_c3L2, cardNo_c3L3, cardNo_c3L4],
    [cardNo_c4L1, cardNo_c4L2, cardNo_c4L3, cardNo_c4L4],
    [cardNo_c5L1, cardNo_c5L2, cardNo_c5L3, cardNo_c5L4]];
    
const ace = "<img title=\"ace_card\" src=\"cards_images/aceCard.png\">",
      king = "<img title=\"king_card\" src=\"cards_images/kingCard.png\">",
      queen = "<img title=\"queen_card\" src=\"cards_images/queenCard.png\">",
      jack = "<img title=\"jack_card\" src=\"cards_images/jackCard.png\">",
      spade = "<img title=\"spade_card\" src=\"cards_images/spadeCard.png\">",
      flower = "<img title=\"flower_card\" src=\"cards_images/flowerCard.png\">",
      heart = "<img title=\"heart_card\" src=\"cards_images/heartCard.png\">",
      diamond = "<img title=\"diamond_card\" src=\"cards_images/diamondCard.png\">",
      scatter = "<img title=\"scatter\" src=\"cards_images/super-ace-03.png\">",
      cardBack = "<img title=\"scatter\" src=\"cards_images/cardBack.png\">",
      wildCard = "<img title=\"wild\" src=\"cards_images/wild.png\">",
      red_wildCard = "<img title=\"red_wild\" src=\"cards_images/red_wildCard.png\">";
      
const golden_ace = "<img title=\"ace_card\" src=\"cards_images/golden_aceCard.png\">",
      golden_king = "<img title=\"king_card\" src=\"cards_images/golden_kingCard.png\">",
      golden_queen = "<img title=\"queen_card\" src=\"cards_images/golden_queenCard.png\">",
      golden_jack = "<img title=\"jack_card\" src=\"cards_images/golden_jackCard.png\">",
      golden_spade = "<img title=\"spade_card\" src=\"cards_images/golden_spadeCard.png\">",
      golden_flower = "<img title=\"flower_card\" src=\"cards_images/golden_flowerCard.png\">",
      golden_heart = "<img title=\"heart_card\" src=\"cards_images/golden_heartCard.png\">",
      golden_diamond = "<img title=\"diamond_card\" src=\"cards_images/golden_diamondCard.png\">";
      
let random, price, current_Price, total_price, scateerNo, free_spins = 0, isScatter = false, limit_loop = 100;

const cards = [ace, king, queen, jack, spade, flower, heart, diamond];

let golden_cards = [golden_ace, golden_king, golden_queen, golden_jack,
                golden_spade, golden_flower, golden_heart, golden_diamond];

let columns = [],
    row = [],
    goldenCard_columns = [],
    goldenCard_row = [],
    cardIndex = 0,
    scatterNo = 0;
let spinInterval, isScatter_Ongoing = false;

getRandomNum();//----------------------------------------------start---------------
for(i = 0; i < red_wildCards.length; i++){
  for(j = 0; j < red_wildCards[0].length; j++){
    for(k = 0; k < red_wildCards[0][0].length; k++){
       red_wildCards[i][j][k].innerHTML == "<img title=\"diamond_card\" src=\"cards_images/red_wildCard.png\">";
    }
  }
}
spinBtn.addEventListener("click", ()=> {//-----------------clicked-----------
  if(free_spins >= 1){
    isScatter_Ongoing = true;
    useFreeSpins();
  }else{
    isScatter_Ongoing = false;
    roll();
  }
})
function useFreeSpins(){//--------------------------------check_error
  if (free_spins <= 0) return
  free_spins--;
  roll();
}

function roll(){
  getRandomNum();
  addAnimation();
  spinBtn.disabled = true;
  setTimeout(removeAnimation, 950);
  columns = [];
  row = [];
  goldenCard_columns = [];
  goldenCard_row = [];
  cardIndex = 0;
  scatterNo = 0;
  isScatter = false;
  checkScatter();//console.log(scatterNo+" "+free_spins)
  setTimeout(checkColumn1, 950);
  
}
function getRandomNum(){//---------------------------geting-RandomNo
  
  for(i=0;i<slotsRan.length;i++){
    for(j = 0;j< slotsRan[0].length;j++){
      //slotsRan[i][j] = Math.floor(Math.random() * 8) + 1;
      random = Math.floor(Math.random() * 520) + 1;
      slotsRan[i][j] = random;
      randomNo_to_cardNo(random,i,j);
      
    }
  }
  for (i = 0; i < slotsRan.length; i++) {
    for(j = 0;j< slotsRan[0].length;j++){
      switchRandom(slotsRan[i][j], slots[i][j]);
    }
  }
}
function randomNo_to_cardNo(random,column,row){//---switching-Random-number-toCardNo
  if (random <= 63) {
    slotsCardNo[column][row] = 1;
  } else if (random == 64) {
    slotsCardNo[column][row] = 1.5;
  } else if (random <= 127) {
    slotsCardNo[column][row] = 2;
  } else if (random == 128) {
    slotsCardNo[column][row] = 2.5;
  } else if (random <= 191) {
    slotsCardNo[column][row] = 3;
  } else if (random == 192) {
    slotsCardNo[column][row] = 3.5;
  } else if (random <= 255) {
    slotsCardNo[column][row] = 4;
  } else if (random == 256) {
    slotsCardNo[column][row] = 4.5;
  } else if (random <= 319) {
    slotsCardNo[column][row] = 5;
  } else if (random == 320) {
    slotsCardNo[column][row] = 5.5;
  } else if (random <= 383) {
    slotsCardNo[column][row] = 6;
  } else if (random == 384) {
    slotsCardNo[column][row] = 6.5;
  } else if (random <= 447) {
    slotsCardNo[column][row] = 7;
  } else if (random == 64) {
    slotsCardNo[column][row] = 7.5;
  } else if (random <= 511) {
    slotsCardNo[column][row] = 8;
  } else if (random == 512) {
    slotsCardNo[column][row] = 8.5;
  } else if (random <= 520) {
    slotsCardNo[column][row] = 9;
  } else if (random == 521) {
    slotsCardNo[column][row] = "WILD";
  } else if (random == 522) {
    slotsCardNo[column][row] = "WILD";
  }
}
function switchRandom(randomNo, slotName){//---switching-Random-number-toCard
  if (randomNo <= 63) {
    slotName.innerHTML = ace;
  } else if (randomNo == 64) {
    slotName.innerHTML = golden_ace;
  } else if (randomNo <= 127) {
    slotName.innerHTML = king;
  } else if (randomNo == 128) {
    slotName.innerHTML = golden_king;
  } else if (randomNo <= 191) {
    slotName.innerHTML = queen;
  } else if (randomNo == 192) {
    slotName.innerHTML = golden_queen;
  } else if (randomNo <= 255) {
    slotName.innerHTML = jack;
  } else if (randomNo == 256) {
    slotName.innerHTML = golden_jack;
  } else if (randomNo <= 319) {
    slotName.innerHTML = spade;
  } else if (randomNo == 320) {
    slotName.innerHTML = golden_spade;
  } else if (randomNo <= 383) {
    slotName.innerHTML = flower;
  } else if (randomNo == 384) {
    slotName.innerHTML = golden_flower;
  } else if (randomNo <= 447) {
    slotName.innerHTML = heart;
  } else if (randomNo == 64) {
    slotName.innerHTML = golden_heart;
  } else if (randomNo <= 511) {
    slotName.innerHTML = diamond;
  } else if (randomNo == 512) {
    slotName.innerHTML = golden_diamond;
  } else if (randomNo <= 520) {
    slotName.innerHTML = scatter;
  } else if (randomNo == 521) {
    slotName.innerHTML = wildCard;
  } else if (randomNo == 522) {
    slotName.innerHTML = red_wildCard;
  }
}
function flipCard( slotName) { //---switching-Random-number-toCard
  slotName.innerHTML = cardBack;
}
function addAnimation(){
  for(i=0;i<slots.length;i++){
    for(j=0;j<slots[0].length;j++){
      slots[i][j].classList.add(slotsAnimations[i][j])
    }
  }
}
function removeAnimation(){
  if(!isScatter_Ongoing) spinBtn.disabled = false;
  
  for(i=0;i<slots.length;i++){
    for(j=0;j<slots[0].length;j++){
      slots[i][j].classList.remove(slotsAnimations[i][j])
    }
  }
}

function checkScatter(){
  for(i = 0;i < 5;i++){
    if (slotsCardNo[0][i] == 9) scatterNo += 1;
    if (slotsCardNo[1][i] == 9) scatterNo += 1;
    if (slotsCardNo[2][i] == 9) scatterNo += 1;
    if (slotsCardNo[3][i] == 9) scatterNo += 1;
    if (slotsCardNo[4][i] == 9) scatterNo += 1;
  }
  if(scatterNo >= 3 && isScatter === false){
    isScatter = true;
    free_spins += 10;
    console.log("scatter")
  }
 
}

function checkColumn1(){
  if (cardIndex > 7) {
    spinBtn.disabled = true;
    setTimeout( reRoll, 100);
  }else{
    for(i = 0;i <= 5;i++){//console.log("c1 "+i+" "+slotsRan[0][i]+" "+(cardIndex+1)+" "+cards[cardIndex]);
      
      if (cardIndex  > 7) {
        break;
      }else if(i == 5){
        cardIndex += 1;
        checkColumn1();
        break;
      }else if(slotsCardNo[0][i] == cardIndex +1 || slotsCardNo[0][i] =="WILD" || slotsCardNo[0][i] == cardIndex +1.5){
        //console.log("c1 have "+cards[cardIndex]+" i="+i);
        //console.log(slots[0][i]);
        checkColumn2();
        break;
      }
    }
  }
}
function checkColumn2(){
  
  for (i = 0; i <= 4; i++) {//console.log("c2 "+i+" "+slotsRan[1][i]+" "+(cardIndex+1)+" "+cards[cardIndex]);
    if (i == 4) {
      cardIndex += 1;
      checkColumn1();
      break;
    }else if(slotsCardNo[1][i] == cardIndex + 1 || slotsCardNo[1][i] == "WILD" || slotsCardNo[1][i] == cardIndex +1.5){
      //console.log("c1,c2 have "+cards[cardIndex])
      checkColumn3();
      break;
    
    }
  }
}
function checkColumn3(){
  for (i = 0; i <= 4; i++) {//console.log("c3 "+i+" "+slotsRan[2][i]+" "+(cardIndex+1)+" "+cards[cardIndex]);
    if (i == 4) {
      cardIndex += 1;
      checkColumn1();
      break;
    }else if(slotsCardNo[2][i] == cardIndex + 1 || slotsCardNo[2][i] == "WILD" || slotsCardNo[2][i] == cardIndex +1.5){
      //console.log("c1,c2,c3 have "+cards[cardIndex]);
      checkColumn4();
      break;
    }
  }
}
function checkColumn4() {
  for (i = 0; i <= 4; i++) { //console.log("c3 "+i+" "+slotsRan[2][i]+" "+(cardIndex+1)+" "+cards[cardIndex]);
    if (i == 4) {
      check3Column_goldenCard_ReRoll(golden_cards[cardIndex]);
      check3ColumnReRoll(cards[cardIndex]);
      check3ColumnReRoll(wildCard);
      check3ColumnReRoll(red_wildCard);
      cardIndex += 1;
      checkColumn1();
      break;
    }else if(slotsCardNo[3][i] == cardIndex + 1.5 || slotsCardNo[3][i] == "WILD" || slotsCardNo[3][i] == cardIndex +1){
      checkGoldenCard_ReRoll(golden_cards[cardIndex]);
      checkReRoll(cards[cardIndex]);
      checkReRoll(wildCard);
      checkReRoll(red_wildCard);
      cardIndex += 1;
      checkColumn1();
      break;
    }
  }
}
function check3Column_goldenCard_ReRoll(card){
  for (i = 0; i <= 2; i++) {
    for (j = 0; j < slots[0].length; j++) { //console.log(slots[i][j].innerHTML +"--"+ card);
      if (slots[i][j].innerHTML == card) {
        goldenCard_columns += i;
        goldenCard_row += j;
        //console.log(columns+"--"+row);
      }
    }
  }
}
function check3ColumnReRoll(card) {
  //console.log("c1,c2,c3 have "+card);
  for (i = 0; i <= 2; i++) {
    for (j = 0; j < slots[0].length; j++) { //console.log(slots[i][j].innerHTML +"--"+ card);
      if (slots[i][j].innerHTML == card) {
        columns += i;
        row += j;
        //console.log(columns+"--"+row);
      }
    }
  }
}
function checkGoldenCard_ReRoll(card) {
  //console.log("c1,c2,c3,c4 have "+card);
  for (i = 0; i < slots.length; i++) {
    for (j = 0; j < slots[0].length; j++) { //console.log(slots[i][j].innerHTML +"--"+ card);
      if (slots[i][j].innerHTML == card) {
        goldenCard_columns += i;
        goldenCard_row += j;
        //console.log(columns+"--"+row);
      }
    }
  }
}
function checkReRoll(card){
  //console.log("c1,c2,c3,c4 have "+card);
  for(i=0;i<slots.length;i++){
    for(j=0;j<slots[0].length;j++){//console.log(slots[i][j].innerHTML +"--"+ card);
      if(slots[i][j].innerHTML == card){
        columns += i;
        row += j;
        //console.log(columns+"--"+row);
      }
    }
  }
}
function reRoll(){
  //alert(columns.length)//--------------------------------check
//console.log(goldenCard_columns.length)
  if(columns.length == 0){//-----------end--------------
    if(isScatter_Ongoing) setTimeout(useFreeSpins,700);//-----------scatter--------------
    spinBtn.disabled = false;
    return;
  }
  spinBtn.disabled = true;
  if(goldenCard_columns.length != 0){
    for (i = 0; i < goldenCard_columns.length; i++) {
      //console.log(slots[columns[i]][row[i]]+"-"+slotsAnimations[columns[i]][row[i]]);
      slots[goldenCard_columns[i]][goldenCard_row[i]].classList.add('rotateCard');
      random = Math.floor(Math.random() * 2) + 1;
      slotsRan[goldenCard_columns[i]][goldenCard_row[i]] = (random + 520);
      switch_to_wild(i)
      if(random == 2){
        for(j = 0; j < 3; j++){
          random = Math.floor(Math.random() * 20);console.log(random + 1)
          change_redWildsPosition(i,j, random);
          
        }
        
      }
      setTimeout(rotateAnimation, 1350);
    }
  }
  for (i = 0; i < columns.length; i++) { 
    //console.log(slots[columns[i]][row[i]]+"-"+slotsAnimations[columns[i]][row[i]]);
    slots[columns[i]][row[i]].classList.add(slotsReRollAnimations[columns[i]][row[i]]);
    random = Math.floor(Math.random() * 520) + 1;//=---------------check
    slotsRan[columns[i]][row[i]] = random;
    
    if(columns[i] == 0 && row[i] == 3){
      switchRandom_again(i,330)
    }else if(columns[i] == 0 && row[i] == 2 || 
             columns[i] == 1 && row[i] == 3){
      switchRandom_again(i,385)
    }else if(columns[i] == 0 && row[i] == 1 ||
             columns[i] == 1 && row[i] == 2 ||
             columns[i] == 2 && row[i] == 3){
      switchRandom_again(i,440)
    }else if (columns[i] == 0 && row[i] == 0 ||
              columns[i] == 1 && row[i] == 1 ||
              columns[i] == 2 && row[i] == 2 ||
              columns[i] == 3 && row[i] == 3) {
      switchRandom_again(i,495)
    }else if (columns[i] == 1 && row[i] == 0 ||
              columns[i] == 2 && row[i] == 1 ||
              columns[i] == 3 && row[i] == 2 ||
              columns[i] == 4 && row[i] == 3) {
      switchRandom_again(i,550)
    }else if (columns[i] == 2 && row[i] == 0 ||
              columns[i] == 3 && row[i] == 1 ||
              columns[i] == 4 && row[i] == 2) {
      switchRandom_again(i,605)
    }else if (columns[i] == 3 && row[i] == 0 ||
              columns[i] == 4 && row[i] == 1 ) {
      switchRandom_again(i,660)
    }else if (columns[i] == 4 && row[i] == 0 ) {
      switchRandom_again(i,715)
    }else{
      switchRandom_again(i,560)
    }
    setTimeout(removeReRollAnimation, 1350);
  }
  setTimeout(() => {
    columns = [];
    row = [];
    goldenCard_columns = [];
    goldenCard_row = [];
    cardIndex = 0;
    scatterNo = 0;
    checkScatter();//console.log(scatterNo+" "+free_spins)
    checkColumn1();
  }, 1400);
  
  function switchRandom_again(i,timeoutTime){
    setTimeout(() => {
      switchRandom(slotsRan[columns[i]][row[i]], slots[columns[i]][row[i]]);
      randomNo_to_cardNo(slotsRan[columns[i]][row[i]], columns[i], row[i]);
    }, timeoutTime);
  }
  function switch_to_wild(i) {
    setTimeout(() => {
      flipCard(slots[goldenCard_columns[i]][goldenCard_row[i]]);
    }, 195);
    setTimeout(() => {
      switchRandom(slotsRan[goldenCard_columns[i]][goldenCard_row[i]],
      slots[goldenCard_columns[i]][goldenCard_row[i]]);
      randomNo_to_cardNo(slotsRan[goldenCard_columns[i]][goldenCard_row[i]], goldenCard_columns[i], goldenCard_row[i]);
    }, 1105);
  }
  function change_redWildsPosition(i,j, random) {
    setTimeout(() => {
      red_wildCards[goldenCard_columns[i]][goldenCard_row[i]][j].classList.add(slotsPosition[random]);
      
    }, 1170);
    setTimeout(() => {
      red_wildCards[goldenCard_columns[i]][goldenCard_row[i]][j].classList.remove(slotsPosition[random]);
    },1350)
    setTimeout(() => {
      switch(random){
        case 1: slotsRan[0][0] = 522; switch3RandomCard(0, 0); break;
        case 2: slotsRan[1][0] = 522; switch3RandomCard(1, 0); break;
        case 3: slotsRan[2][0] = 522; switch3RandomCard(2, 0); break;
        case 4: slotsRan[3][0] = 522; switch3RandomCard(3, 0); break;
        case 5: slotsRan[4][0] = 522; switch3RandomCard(4, 0); break;
        case 6: slotsRan[0][1] = 522; switch3RandomCard(0, 1); break;
        case 7: slotsRan[1][1] = 522; switch3RandomCard(1, 1); break;
        case 8: slotsRan[2][1] = 522; switch3RandomCard(2, 1); break;
        case 9: slotsRan[3][1] = 522; switch3RandomCard(3, 1); break;
        case 10: slotsRan[4][1] = 522; switch3RandomCard(4, 1); break;
        case 11: slotsRan[0][2] = 522; switch3RandomCard(0, 2); break;
        case 12: slotsRan[1][2] = 522; switch3RandomCard(1, 2); break;
        case 13: slotsRan[2][2] = 522; switch3RandomCard(2, 2); break;
        case 14: slotsRan[3][2] = 522; switch3RandomCard(3, 2); break;
        case 15: slotsRan[4][2] = 522; switch3RandomCard(4, 2); break;
        case 16: slotsRan[0][3] = 522; switch3RandomCard(0, 3); break;
        case 17: slotsRan[1][3] = 522; switch3RandomCard(1, 3); break;
        case 18: slotsRan[2][3] = 522; switch3RandomCard(2, 3); break;
        case 19: slotsRan[3][3] = 522; switch3RandomCard(3, 3); break;
        case 20: slotsRan[4][3] = 522; switch3RandomCard(4, 3); break;
        default:break;
      }
     
    }, 1300);
  }
  function switch3RandomCard(column, row){
    switchRandom(slotsRan[column][row], slots[column][row]);
    randomNo_to_cardNo(slotsRan[column][row], column, row);
  }
}

function removeReRollAnimation() {
  if(!isScatter_Ongoing) spinBtn.disabled = false;
  for (i = 0; i < columns.length; i++) {
    slots[columns[i]][row[i]].classList.remove(slotsReRollAnimations[columns[i]][row[i]]);
  }
}
function rotateAnimation() {
  if (!isScatter_Ongoing) spinBtn.disabled = false;
  for (i = 0; i < goldenCard_columns.length; i++) {
    slots[goldenCard_columns[i]][goldenCard_row[i]].classList.remove('rotateCard');
  }
}
