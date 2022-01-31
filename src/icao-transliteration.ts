// spec can be found here:
// https://www.icao.int/publications/Documents/9303_p3_cons_en.pdf

// prettier-ignore
// noinspection NonAsciiCharacters
const transliterationMapping: Record<number, string|string[]> = {
  0x0020: '<',
  0x002D: '<',
  0x00C0: 'A',
  0x00C1: 'A',
  0x00C2: 'A',
  0x00C3: 'A',
  0x00C4: 'AE',
  0x00C5: 'AA',
  0x00C6: 'AE',
  0x00C7: 'C',
  0x00C8: 'E',
  0x00C9: 'E',
  0x00CA: 'E',
  0x00CB: 'E',
  0x00CC: 'I',
  0x00CD: 'I',
  0x00CE: 'I',
  0x00CF: 'I',
  0x00D0: 'D',
  0x00D1: 'N',
  0x00D2: 'O',
  0x00D3: 'O',
  0x00D4: 'O',
  0x00D5: 'O',
  0x00D6: 'OE',
  0x00D8: 'OE',
  0x00D9: 'U',
  0x00DA: 'U',
  0x00DB: 'U',
  0x00DC: 'UE',
  0x00DD: 'Y',
  0x00DE: 'TH',
  0x0100: 'A',
  0x0102: 'A',
  0x0104: 'A',
  0x0106: 'C',
  0x0108: 'C',
  0x010A: 'C',
  0x010C: 'C',
  0x010E: 'D',
  0x0110: 'D',
  0x0112: 'E',
  0x0114: 'E',
  0x0116: 'E',
  0x0118: 'E',
  0x011A: 'E',
  0x011C: 'G',
  0x011E: 'G',
  0x0120: 'G',
  0x0122: 'G',
  0x0124: 'H',
  0x0126: 'H',
  0x0128: 'I',
  0x012A: 'I',
  0x012C: 'I',
  0x012E: 'I',
  0x0130: 'I',
  0x0131: 'I',
  0x0132: 'IJ',
  0x0134: 'J',
  0x0136: 'K',
  0x0139: 'L',
  0x013B: 'L',
  0x013D: 'L',
  0x013F: 'L',
  0x0141: 'L',
  0x0143: 'N',
  0x0145: 'N',
  0x0147: 'N',
  0x014A: 'N',
  0x014C: 'O',
  0x014E: 'O',
  0x0150: 'O',
  0x0152: 'OE',
  0x0154: 'R',
  0x0156: 'R',
  0x0158: 'R',
  0x015A: 'S',
  0x015C: 'S',
  0x015E: 'S',
  0x0160: 'S',
  0x0162: 'T',
  0x0164: 'T',
  0x0166: 'T',
  0x0168: 'U',
  0x016A: 'U',
  0x016C: 'U',
  0x016E: 'U',
  0x0170: 'U',
  0x0172: 'U',
  0x0174: 'W',
  0x0176: 'Y',
  0x0178: 'Y',
  0x0179: 'Z',
  0x017B: 'Z',
  0x017D: 'Z',
  0x1E9E: 'SS',
  0x0621: 'XE',
  0x0622: 'XAA',
  0x0623: 'XAE',
  0x0624: 'U',
  0x0625: 'I',
  0x0626: 'XI',
  0x0627: 'A',
  0x0628: 'B',
  0x0629: ['XTA', 'XAH'], // XTA is used generally, except if teh marbuta occurs at the end of the name component, in which case XAH is used.
  0x062A: 'T',
  0x062B: 'XTH',
  0x062C: 'J',
  0x062D: 'XH',
  0x062E: 'XKH',
  0x062F: 'D',
  0x0630: 'XDH',
  0x0631: 'R',
  0x0632: 'Z',
  0x0633: 'S',
  0x0634: 'XSH',
  0x0635: 'XSS',
  0x0636: 'XDZ',
  0x0637: 'XTT',
  0x0638: 'XZZ',
  0x0639: 'E',
  0x063A: 'G',
  0x0640: '', // not encoded
  0x0641: 'F',
  0x0642: 'Q',
  0x0643: 'K',
  0x0644: 'L',
  0x0645: 'M',
  0x0646: 'N',
  0x0647: 'H',
  0x0648: 'W',
  0x0649: 'XAY',
  0x064A: 'Y',
  0x064B: '', // not encoded
  0x064C: '', // not encoded
  0x064D: '', // not encoded
  0x064E: '', // not encoded
  0x064F: '', // not encoded
  0x0650: '', // not encoded
  0x0651: '', // "shadda" denotes doubling, next latin character or sequence is repeated?
  0x0652: '', // not encoded
  0x0670: '', // not encoded
  0x0671: 'XXA',
  0x0679: 'XXT',
  0x067C: 'XRT',
  0x067E: 'P',
  0x0681: 'XKE',
  0x0685: 'XXH',
  0x0686: 'XC',
  0x0688: 'XXD',
  0x0689: 'XDR',
  0x0691: 'XXR',
  0x0693: 'XRR',
  0x0696: 'XRX',
  0x0698: 'XJ',
  0x069A: 'XXS',
  0x069C: '', // not encoded
  0x06A2: '', // not encoded
  0x06A7: '', // not encoded
  0x06A8: '', // not encoded
  0x06A9: 'XKK',
  0x06AB: 'XXK',
  0x06AD: 'XNG',
  0x06AF: 'XGG',
  0x06BA: 'XNN',
  0x06BC: 'XXN',
  0x06BE: 'XDO',
  0x06C0: 'XYH',
  0x06C1: 'XXG',
  0x06C2: 'XGE',
  0x06C3: 'XTG',
  0x06CC: 'XYA',
  0x06CD: 'XXY',
  0x06D0: 'Y',
  0x06D2: 'XYB',MRZ
  0x06D3: 'XBE',
};

/**
 * Transliterates a string to machine-readable zone using ICAO 9393 P3 Transliteration Mapping
 * hint: This does not respect different variants for serbian/bulgarian/ukrainian etc. - it always uses the first recommended transliteration
 * @param input
 */
function transliterateMRZ(input: string): string {
  const mappedOutputCharacters: string[] = [];
  const inputUppercase = input
    .trim() // trim start and end
    .toUpperCase() // uppercase normalization
    .replace(/([\u0020|\u002D])+/g, (match) => {
      return match[0]; // de-duplicate spaces and dashes
    });
  for (let i = 0; i < inputUppercase.length; i++) {
    let charCode = inputUppercase.charCodeAt(i);
    let n = 1;
    // if it's "shadda" for double notion and there's at least one more character
    if (charCode == 0x0651 && i < inputUppercase.length - 1) {
      i++;
      charCode = inputUppercase.charCodeAt(i);
      n = 2;
    }
    for (let j = 0; j < n; j++) {
      const transliterated = transliterationMapping[charCode];
      if (transliterated !== undefined) {
        if (Array.isArray(transliterated)) {
          if (i === inputUppercase.length - 1) {
            mappedOutputCharacters.push(transliterated[1]);
          } else {
            mappedOutputCharacters.push(transliterated[0]);
          }
        } else {
          mappedOutputCharacters.push(transliterated);
        }
      } else {
        const upperCharacter = String.fromCharCode(charCode);
        if (upperCharacter.match(/^[A-Z]$/)) {
          mappedOutputCharacters.push(upperCharacter);
        }
      }
    }
  }
  return mappedOutputCharacters.join('');
}
