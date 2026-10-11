// Unit tests for the pure validation rules in frontend/js/validation.js
// Run with:  node --test frontend/tests/
'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const V = require('../js/validation.js');

const MB = 1024 * 1024;

test('empty required fields are rejected', () => {
    assert.match(V.validateName(''), /Enter/);
    assert.match(V.validateRollNumber('   '), /Enter/);
    assert.match(V.validateEmail(''), /Enter/);
    assert.match(V.validateCourse('', ['BE-CSE']), /Select/);
    assert.match(V.validatePassword(''), /Create a password/);
    assert.match(V.validateConfirmPassword('Abc!2345', ''), /Re-enter/);
    assert.match(V.validateFile(null, V.FILE_RULES.profile_photo), /Choose/);
});

test('optional ID proof may be empty', () => {
    assert.equal(V.validateFile(null, V.FILE_RULES.id_proof), '');
});

test('name rules', () => {
    assert.equal(V.validateName('Priya Raman'), '');
    assert.equal(V.validateName("D'Souza-Nair K."), '');
    assert.equal(V.validateName('முருகன்'), '');               // Tamil letters + combining marks
    assert.notEqual(V.validateName('A'), '');
    assert.notEqual(V.validateName('Robert<script>'), '');
    assert.notEqual(V.validateName('1234'), '');
    assert.notEqual(V.validateName('x'.repeat(101)), '');
});

test('roll number rules', () => {
    assert.equal(V.validateRollNumber('23cs045'), '');
    assert.notEqual(V.validateRollNumber('23 CS'), '');
    assert.notEqual(V.validateRollNumber("23CS' OR 1=1"), '');
    assert.notEqual(V.validateRollNumber('ABC'), '');
    assert.notEqual(V.validateRollNumber('A'.repeat(16)), '');
});

test('invalid email formats are rejected', () => {
    for (const bad of ['plainaddress', 'name@', '@example.com', 'name@example', 'na me@example.com',
        'name..dot@example.com', '.name@example.com', 'name@-example.com', 'name@example.c']) {
        assert.notEqual(V.validateEmail(bad), '', bad);
    }
    for (const good of ['student@example.edu', 'first.last+tag@college.ac.in', 'a_b@sub.domain.org']) {
        assert.equal(V.validateEmail(good), '', good);
    }
    assert.notEqual(V.validateEmail('a'.repeat(140) + '@example.com'), '');
});

test('course must be in the allowed list', () => {
    assert.equal(V.validateCourse('BE-CSE', ['BE-CSE', 'BE-ECE']), '');
    assert.notEqual(V.validateCourse('HACK', ['BE-CSE']), '');
});

test('weak passwords are rejected with an explanation', () => {
    const weak = V.assessPassword('abc');
    assert.equal(weak.valid, false);
    assert.equal(weak.level, 1);
    assert.match(weak.message, /at least 8 characters/);

    assert.match(V.validatePassword('alllowercase1!'), /uppercase/);
    assert.match(V.validatePassword('NoDigits!!'), /number/);
    assert.match(V.validatePassword('NoSymbol123'), /symbol/);
    assert.match(V.validatePassword('Has Space1!'), /spaces/);
    assert.match(V.validatePassword('P@ssw0rd'), /too common/);
    assert.match(V.validatePassword('Xx!23CS045yy', { rollNumber: '23cs045' }), /roll number/);
    assert.match(V.validatePassword('Aa1!' + 'x'.repeat(70)), /64 characters/);
});

test('strong password is accepted and graded', () => {
    const good = V.assessPassword('Kpr!Tech26');
    assert.equal(good.valid, true);
    assert.equal(good.level, 3);
    const strong = V.assessPassword('Kpr!Tech2026#Lab');
    assert.equal(strong.valid, true);
    assert.equal(strong.label, 'Strong');
});

test('password confirmation must match', () => {
    assert.equal(V.validateConfirmPassword('Kpr!Tech26', 'Kpr!Tech26'), '');
    assert.match(V.validateConfirmPassword('Kpr!Tech26', 'Kpr!Tech27'), /do not match/);
});

test('invalid file types are rejected', () => {
    const rules = V.FILE_RULES.profile_photo;
    assert.match(V.validateFile({ name: 'cv.pdf', size: 1000, type: 'application/pdf' }, rules), /JPG, PNG or WEBP/);
    assert.match(V.validateFile({ name: 'shell.php', size: 10, type: 'application/x-php' }, rules), /must be/);
    assert.match(V.validateFile({ name: 'shell.php.jpg', size: 10, type: 'image/jpeg' }, rules), /unsafe/);
    assert.match(V.validateFile({ name: 'photo.jpg', size: 10, type: 'text/html' }, rules), /unsupported/);
    assert.match(V.validateFile({ name: 'empty.png', size: 0, type: 'image/png' }, rules), /empty/);
});

test('oversized files are rejected', () => {
    assert.match(V.validateFile({ name: 'big.jpg', size: 3 * MB, type: 'image/jpeg' }, V.FILE_RULES.profile_photo), /maximum size is 2 MB/);
    assert.match(V.validateFile({ name: 'big.pdf', size: 6 * MB, type: 'application/pdf' }, V.FILE_RULES.id_proof), /maximum size is 5 MB/);
    assert.equal(V.validateFile({ name: 'ok.pdf', size: 5 * MB, type: 'application/pdf' }, V.FILE_RULES.id_proof), '');
});

test('file signatures are detected from bytes, not names', () => {
    assert.equal(V.detectSignature(Uint8Array.from([0xFF, 0xD8, 0xFF, 0xE0])), 'image/jpeg');
    assert.equal(V.detectSignature(Uint8Array.from([0x89, 0x50, 0x4E, 0x47, 0x0D, 0x0A])), 'image/png');
    assert.equal(V.detectSignature(Uint8Array.from(Buffer.from('%PDF-1.7'))), 'application/pdf');
    assert.equal(V.detectSignature(Uint8Array.from(Buffer.from('RIFF\x00\x00\x00\x00WEBP', 'latin1'))), 'image/webp');
    assert.equal(V.detectSignature(Uint8Array.from(Buffer.from('<?php echo 1; ?>'))), null);
});

test('a fully valid submission passes every rule', () => {
    const data = {
        name: 'Priya Raman', roll: '23CS045', email: 'priya.raman@example.edu', course: 'BE-CSE',
        password: 'Kpr!Tech2026', photo: { name: 'me.png', size: 200 * 1024, type: 'image/png' },
        proof: { name: 'aadhaar.pdf', size: MB, type: 'application/pdf' }
    };
    assert.equal(V.validateName(data.name), '');
    assert.equal(V.validateRollNumber(data.roll), '');
    assert.equal(V.validateEmail(data.email), '');
    assert.equal(V.validateCourse(data.course, ['BE-CSE']), '');
    assert.equal(V.validatePassword(data.password, { rollNumber: data.roll }), '');
    assert.equal(V.validateConfirmPassword(data.password, data.password), '');
    assert.equal(V.validateFile(data.photo, V.FILE_RULES.profile_photo), '');
    assert.equal(V.validateFile(data.proof, V.FILE_RULES.id_proof), '');
});
