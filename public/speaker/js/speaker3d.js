import * as THREE from "three";
import { GLTFLoader } from "three/addons/loaders/GLTFLoader.js";

const container = document.getElementById("avatar3d");
const scene = new THREE.Scene();

const camera = new THREE.PerspectiveCamera(
    40,
    container.clientWidth / container.clientHeight,
    0.1,
    1000
);

const renderer = new THREE.WebGLRenderer({ alpha: true, antialias: true });
renderer.setSize(container.clientWidth, container.clientHeight);
renderer.setPixelRatio(window.devicePixelRatio);
container.appendChild(renderer.domElement);

const light = new THREE.HemisphereLight(0xffffff, 0x444444, 3);
scene.add(light);

let avatar = null;
let mouthMesh = null;
let originalAvatarY = 0; 
let originalMouthY = 0;

const loader = new GLTFLoader();

loader.load(
    "/speaker/models/avatar.glb",
    (gltf) => {
        avatar = gltf.scene;
        scene.add(avatar);

        avatar.updateMatrixWorld(true);

        // 1. Si sax ah u xisaabi cabbirka moodeelka Meshy
        const box = new THREE.Box3().setFromObject(avatar);
        const center = box.getCenter(new THREE.Vector3());
        const size = box.getSize(new THREE.Vector3());

        // Moodeelka keen bartamaha saxda ah
        avatar.position.sub(center);

        // 2. Si otomaatig ah u hagaaji fogaanta kamaradda (Auto-Zoom / Framing) iyadoo la eegayo cabbirka avatar-ka
        const maxDim = Math.max(size.x, size.y, size.z);
        const fov = camera.fov * (Math.PI / 180);
        let cameraZ = Math.abs(maxDim / 2 / Math.tan(fov / 2));
        
        // Ku dhufo factor si aad u dejiso inta uu ka fogeynayo (Tusaale: 2.2 waa meel dabiici ah)
        cameraZ *= 2.2; 

        camera.position.set(0, size.y * 0.05, cameraZ);
        camera.lookAt(0, 0, 0);

        originalAvatarY = avatar.position.y; 

        avatar.traverse((child) => {
            if (child.isMesh) {
                child.castShadow = true;
                child.receiveShadow = true;

                const name = child.name.toLowerCase();
                if (
                    name.includes("mouth") ||
                    name.includes("jaw") ||
                    name.includes("lip")
                ) {
                    mouthMesh = child;
                    originalMouthY = child.position.y;
                }
            }
        });

        console.log("Meshy Avatar Auto-Framed Successfully!");
    },
    undefined,
    (error) => {
        console.error("Failed to load avatar.glb", error);
    }
);

let isSpeaking = false;
window.setAvatarSpeaking = (speaking) => {
    isSpeaking = speaking;
};

function animate() {
    requestAnimationFrame(animate);

    if (avatar && isSpeaking) {
        const t = Date.now() * 0.01;
        if (mouthMesh) {
            mouthMesh.position.y = originalMouthY + Math.sin(t * 20) * 0.003;
        } 
        avatar.position.y = originalAvatarY + Math.sin(t * 5) * 0.01;
        avatar.rotation.y = Math.sin(t * 2) * 0.02;

    } else if (avatar && !isSpeaking) {
        avatar.position.y = originalAvatarY;
        avatar.rotation.y = 0;
        if (mouthMesh) mouthMesh.position.y = originalMouthY;
    }

    renderer.render(scene, camera);
}
animate();

window.addEventListener("resize", () => {
    if(container.clientWidth > 0 && container.clientHeight > 0) {
        camera.aspect = container.clientWidth / container.clientHeight;
        camera.updateProjectionMatrix();
        renderer.setSize(container.clientWidth, container.clientHeight);
    }
});